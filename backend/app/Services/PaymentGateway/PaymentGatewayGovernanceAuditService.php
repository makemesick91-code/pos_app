<?php

namespace App\Services\PaymentGateway;

use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingPaymentIntent;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 31 — audits that the payment gateway settlement foundation is wired and
 * safe (PGW-R001..R018). Read-only. Produces PASS/WARN/FAIL signals aggregated
 * into a GO/WATCH/NO_GO. A structural defect (missing table/service, a webhook-
 * verification switch off, a guardrail flipped on, a failed event that marked an
 * invoice paid, a settlement not backed by a payment record, a tenant-mutable
 * gateway route, or a missing rule) is a hard FAIL.
 */
class PaymentGatewayGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_WARN = 'WARN';

    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';

    public const DECISION_WATCH = 'WATCH';

    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->tablesSignal(),
            $this->servicesSignal(),
            $this->rulesSignal(),
            $this->guardrailsSignal(),
            $this->webhookPostureSignal(),
            $this->providerConfigSignal(),
            $this->billingLayerSignal(),
            $this->failedEventNeverPaidSignal(),
            $this->settlementBackedByPaymentSignal(),
            $this->providerReferenceUniqueSignal(),
            $this->mutationRoutesAdminOnlySignal(),
            $this->noTenantMutationRouteSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    private function tablesSignal(): array
    {
        $missing = array_values(array_filter(
            ['tenant_billing_payment_intents', 'tenant_billing_gateway_events'],
            fn (string $t): bool => ! Schema::hasTable($t),
        ));

        return $missing === []
            ? $this->signal('gateway_tables', self::STATUS_PASS, 'Payment intent and gateway event tables present.')
            : $this->signal('gateway_tables', self::STATUS_FAIL, 'Missing gateway tables: '.implode(', ', $missing));
    }

    private function servicesSignal(): array
    {
        $required = [
            PaymentGatewayProviderManager::class,
            PaymentGatewayIntentService::class,
            PaymentGatewayWebhookService::class,
            PaymentGatewaySettlementService::class,
            PaymentGatewayRedactor::class,
            TenantPaymentCollectionService::class,
        ];
        $missing = array_values(array_filter($required, fn (string $c): bool => ! class_exists($c)));

        return $missing === []
            ? $this->signal('gateway_services', self::STATUS_PASS, 'Provider/intent/webhook/settlement/redaction services present.')
            : $this->signal('gateway_services', self::STATUS_FAIL, 'Missing gateway services: '.implode(', ', $missing));
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('payment_gateway_governance.rules', []);
        $expected = [];
        for ($i = 1; $i <= 18; $i++) {
            $expected[] = sprintf('PGW-R%03d', $i);
        }
        $missing = array_values(array_diff($expected, array_keys($rules)));

        return $missing === []
            ? $this->signal('gateway_rules', self::STATUS_PASS, 'PGW-R001..R018 present in config.')
            : $this->signal('gateway_rules', self::STATUS_FAIL, 'Missing rules: '.implode(', ', $missing));
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'live_gateway_call_in_ci_allowed',
            'unsigned_webhook_allowed',
            'failed_event_marks_invoice_paid_allowed',
            'settlement_bypasses_collection_service_allowed',
            'settlement_lifts_manual_suspension_allowed',
            'tenant_route_can_mutate_gateway_state_allowed',
            'secrets_in_gateway_metadata_allowed',
            'duplicate_provider_reference_settlement_allowed',
        ];
        $on = array_values(array_filter($flags, fn (string $f): bool => (bool) config('payment_gateway_governance.'.$f, false)));

        return $on === []
            ? $this->signal('gateway_guardrails', self::STATUS_PASS, 'All gateway guardrail flags are false.')
            : $this->signal('gateway_guardrails', self::STATUS_FAIL, 'Unsafe guardrail(s) enabled: '.implode(', ', $on));
    }

    private function webhookPostureSignal(): array
    {
        $required = ['webhook_signature_required', 'replay_protection_required', 'idempotency_required', 'raw_payload_redaction_enabled'];
        $off = array_values(array_filter($required, fn (string $f): bool => ! (bool) config('payment_gateway_governance.'.$f, false)));

        return $off === []
            ? $this->signal('webhook_posture', self::STATUS_PASS, 'Signature/replay/idempotency/redaction all required.')
            : $this->signal('webhook_posture', self::STATUS_FAIL, 'Weakened webhook posture: '.implode(', ', $off));
    }

    private function providerConfigSignal(): array
    {
        $default = (string) config('payment_gateway_governance.default_provider', '');
        $providers = (array) config('payment_gateway_governance.providers', []);

        if ($default === '' || ! isset($providers[$default])) {
            return $this->signal('provider_config', self::STATUS_FAIL, 'No explicit/valid default provider configured.');
        }

        // In the foundation, the default must be a non-live provider so CI never
        // makes a real call unless live is deliberately enabled.
        $live = (bool) config('payment_gateway_governance.live_gateway_enabled', false);
        if ($live) {
            return $this->signal('provider_config', self::STATUS_WARN, "Live gateway enabled (default '{$default}').");
        }

        return $this->signal('provider_config', self::STATUS_PASS, "Default provider '{$default}' configured; live gateway disabled.");
    }

    private function billingLayerSignal(): array
    {
        $ok = class_exists(TenantPaymentCollectionService::class)
            && Schema::hasTable('tenant_billing_invoices')
            && Schema::hasTable('tenant_billing_payments');

        return $ok
            ? $this->signal('billing_layer', self::STATUS_PASS, 'Sprint 30 billing layer present (PGW-R017).')
            : $this->signal('billing_layer', self::STATUS_FAIL, 'Sprint 30 billing layer missing — settlement cannot be compatible.');
    }

    private function failedEventNeverPaidSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_payment_intents')) {
            return $this->signal('failed_never_paid', self::STATUS_WARN, 'Intent table not migrated.');
        }

        // A paid intent must have a matching provider_reference and a paid_at — a
        // failed/expired/cancelled intent must never be marked paid.
        $bad = TenantBillingPaymentIntent::query()
            ->whereIn('status', [TenantBillingPaymentIntent::STATUS_FAILED, TenantBillingPaymentIntent::STATUS_EXPIRED, TenantBillingPaymentIntent::STATUS_CANCELLED])
            ->whereNotNull('paid_at')
            ->count();

        return $bad === 0
            ? $this->signal('failed_never_paid', self::STATUS_PASS, 'No failed/expired/cancelled intent is marked paid.')
            : $this->signal('failed_never_paid', self::STATUS_FAIL, "{$bad} terminal intent(s) carry a paid_at.");
    }

    private function settlementBackedByPaymentSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_payment_intents') || ! Schema::hasTable('tenant_billing_payments')) {
            return $this->signal('settlement_backed', self::STATUS_WARN, 'Intent/payment table not migrated.');
        }

        // Every PAID intent must correspond to a recorded Sprint 30 payment on the
        // same invoice — proving settlement went through the collection service.
        $orphans = TenantBillingPaymentIntent::query()
            ->where('status', TenantBillingPaymentIntent::STATUS_PAID)
            ->whereDoesntHave('invoice.payments')
            ->count();

        return $orphans === 0
            ? $this->signal('settlement_backed', self::STATUS_PASS, 'Every paid intent is backed by a recorded payment.')
            : $this->signal('settlement_backed', self::STATUS_FAIL, "{$orphans} paid intent(s) have no recorded payment.");
    }

    private function providerReferenceUniqueSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_payment_intents')) {
            return $this->signal('provider_reference_unique', self::STATUS_WARN, 'Intent table not migrated.');
        }

        $dupes = TenantBillingPaymentIntent::query()
            ->whereNotNull('provider_reference')
            ->selectRaw('provider, provider_reference, COUNT(*) as c')
            ->groupBy('provider', 'provider_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return $dupes === 0
            ? $this->signal('provider_reference_unique', self::STATUS_PASS, 'Provider references are unique per provider.')
            : $this->signal('provider_reference_unique', self::STATUS_FAIL, "{$dupes} provider reference(s) are duplicated.");
    }

    private function mutationRoutesAdminOnlySignal(): array
    {
        $offenders = [];
        $found = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_contains($uri, 'tenant-billing/gateway')) {
                continue;
            }
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }
            $found++;
            if (! in_array('platform.admin', $route->gatherMiddleware(), true)) {
                $offenders[] = $uri;
            }
        }

        $offenders = array_values(array_unique($offenders));
        if ($offenders !== []) {
            return $this->signal('gateway_mutations_admin_only', self::STATUS_FAIL, 'Gateway mutation route(s) not admin-guarded: '.implode(', ', $offenders));
        }

        return $found > 0
            ? $this->signal('gateway_mutations_admin_only', self::STATUS_PASS, "{$found} gateway admin mutation route(s) are platform-admin only.")
            : $this->signal('gateway_mutations_admin_only', self::STATUS_WARN, 'No gateway admin mutation routes registered.');
    }

    private function noTenantMutationRouteSignal(): array
    {
        // The verified provider webhook is the ONLY unauthenticated write path and
        // is not a tenant mutation route. No other public/tenant route may mutate
        // gateway/intent/settlement state (PGW-R015).
        $offenders = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $isGatewayWrite = (str_contains($uri, 'payment-gateway') || str_contains($uri, 'tenant-billing/gateway'))
                && (in_array('POST', $route->methods(), true) || in_array('PUT', $route->methods(), true) || in_array('DELETE', $route->methods(), true));

            if (! $isGatewayWrite) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $isAdmin = in_array('platform.admin', $middleware, true);
            $isWebhook = str_contains($uri, 'payment-gateway') && str_contains($uri, 'webhook');

            if (! $isAdmin && ! $isWebhook) {
                $offenders[] = $uri;
            }
        }

        $offenders = array_values(array_unique($offenders));

        return $offenders === []
            ? $this->signal('no_tenant_gateway_mutation', self::STATUS_PASS, 'No tenant/public gateway mutation route (only admin + verified webhook).')
            : $this->signal('no_tenant_gateway_mutation', self::STATUS_FAIL, 'Unexpected public gateway write route(s): '.implode(', ', $offenders));
    }

    /**
     * @param  array<int, array{status:string}>  $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
