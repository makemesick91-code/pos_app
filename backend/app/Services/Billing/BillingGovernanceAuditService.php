<?php

namespace App\Services\Billing;

use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 30 — audits that the billing invoice/payment foundation is wired and
 * safe (BIL-R001..R014). Read-only. Produces PASS/WARN/FAIL signals aggregated
 * into a GO/WATCH/NO_GO. A structural defect (missing table/service, a duplicate
 * invoice, a negative amount, a non-admin mutation route, a guardrail flipped on,
 * or a missing rule) is a hard FAIL.
 */
class BillingGovernanceAuditService
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
            $this->pricingSignal(),
            $this->rulesSignal(),
            $this->guardrailsSignal(),
            $this->duplicateInvoiceSignal(),
            $this->negativeAmountSignal(),
            $this->invoiceWithoutPlanSignal(),
            $this->paymentAmountSignal(),
            $this->mutationRoutesAdminOnlySignal(),
            $this->auditWiringSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    private function tablesSignal(): array
    {
        $missing = array_values(array_filter(
            ['tenant_billing_invoices', 'tenant_billing_payments'],
            fn (string $t): bool => ! Schema::hasTable($t),
        ));

        return $missing === []
            ? $this->signal('billing_tables', self::STATUS_PASS, 'Invoice and payment tables present.')
            : $this->signal('billing_tables', self::STATUS_FAIL, 'Missing billing tables: '.implode(', ', $missing));
    }

    private function servicesSignal(): array
    {
        $required = [
            BillingPeriodService::class,
            TenantInvoicePricingService::class,
            TenantInvoiceService::class,
            TenantInvoiceStatusService::class,
            TenantPaymentCollectionService::class,
        ];
        $missing = array_values(array_filter($required, fn (string $c): bool => ! class_exists($c)));

        return $missing === []
            ? $this->signal('billing_services', self::STATUS_PASS, 'Billing period/pricing/invoice/payment services present.')
            : $this->signal('billing_services', self::STATUS_FAIL, 'Missing billing services: '.implode(', ', $missing));
    }

    private function pricingSignal(): array
    {
        $planKeys = (array) config('tenant_plan.plan_keys', []);
        $pricing = (array) config('billing_governance.pricing', []);
        $missing = array_values(array_diff($planKeys, array_keys($pricing)));

        return $missing === []
            ? $this->signal('plan_pricing', self::STATUS_PASS, count($pricing).' plan prices configured.')
            : $this->signal('plan_pricing', self::STATUS_FAIL, 'Missing pricing for plans: '.implode(', ', $missing));
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('billing_governance.rules', []);
        $expected = [];
        for ($i = 1; $i <= 16; $i++) {
            $expected[] = sprintf('BIL-R%03d', $i);
        }
        $missing = array_values(array_diff($expected, array_keys($rules)));

        return $missing === []
            ? $this->signal('billing_rules', self::STATUS_PASS, 'BIL-R001..R016 present in config.')
            : $this->signal('billing_rules', self::STATUS_FAIL, 'Missing rules: '.implode(', ', $missing));
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'invoice_amount_from_client_allowed',
            'invoice_without_plan_pricing_allowed',
            'duplicate_invoice_per_period_allowed',
            'failed_payment_marks_invoice_paid_allowed',
            'paid_invoice_lifts_manual_suspension_allowed',
            'renewal_bypasses_invoice_service_allowed',
            'plan_price_change_mutates_issued_invoice_allowed',
            'tenant_route_can_mutate_invoice_state_allowed',
        ];
        $on = array_values(array_filter($flags, fn (string $f): bool => (bool) config('billing_governance.'.$f, false)));

        return $on === []
            ? $this->signal('billing_guardrails', self::STATUS_PASS, 'All billing guardrail flags are false.')
            : $this->signal('billing_guardrails', self::STATUS_FAIL, 'Unsafe guardrail(s) enabled: '.implode(', ', $on));
    }

    private function duplicateInvoiceSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_invoices')) {
            return $this->signal('no_duplicate_invoice', self::STATUS_WARN, 'Invoice table not migrated.');
        }

        $dupes = TenantBillingInvoice::query()
            ->whereNotIn('status', [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED])
            ->select('tenant_id', 'period_key', DB::raw('COUNT(*) as c'))
            ->groupBy('tenant_id', 'period_key')
            ->having('c', '>', 1)
            ->count();

        return $dupes === 0
            ? $this->signal('no_duplicate_invoice', self::STATUS_PASS, 'No duplicate live invoice per tenant/period.')
            : $this->signal('no_duplicate_invoice', self::STATUS_FAIL, "{$dupes} tenant/period(s) have duplicate live invoices.");
    }

    private function negativeAmountSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_invoices')) {
            return $this->signal('no_negative_amount', self::STATUS_WARN, 'Invoice table not migrated.');
        }

        $bad = TenantBillingInvoice::query()->where('total_amount', '<', 0)->count();

        return $bad === 0
            ? $this->signal('no_negative_amount', self::STATUS_PASS, 'No negative invoice amounts.')
            : $this->signal('no_negative_amount', self::STATUS_FAIL, "{$bad} invoice(s) have a negative total.");
    }

    private function invoiceWithoutPlanSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_invoices')) {
            return $this->signal('invoice_has_plan', self::STATUS_WARN, 'Invoice table not migrated.');
        }

        $bad = TenantBillingInvoice::query()->where(fn ($q) => $q->whereNull('plan_key')->orWhere('plan_key', ''))->count();

        return $bad === 0
            ? $this->signal('invoice_has_plan', self::STATUS_PASS, 'Every invoice carries a plan key.')
            : $this->signal('invoice_has_plan', self::STATUS_FAIL, "{$bad} invoice(s) have no plan key.");
    }

    private function paymentAmountSignal(): array
    {
        if (! Schema::hasTable('tenant_billing_payments')) {
            return $this->signal('payment_amount_valid', self::STATUS_WARN, 'Payment table not migrated.');
        }

        $bad = TenantBillingPayment::query()->where('amount', '<=', 0)->count();

        return $bad === 0
            ? $this->signal('payment_amount_valid', self::STATUS_PASS, 'No non-positive payment amounts.')
            : $this->signal('payment_amount_valid', self::STATUS_FAIL, "{$bad} payment(s) have a non-positive amount.");
    }

    private function mutationRoutesAdminOnlySignal(): array
    {
        $offenders = [];
        $found = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // A billing state-mutating route: writes an invoice or payment.
            $isMutation = str_contains($uri, 'billing') && (
                str_contains($uri, 'billing/invoices') && (str_contains($uri, 'generate') || str_contains($uri, 'payments'))
                || str_contains($uri, 'billing/payments/')
            );

            if (! $isMutation || ! in_array('POST', $route->methods(), true)) {
                continue;
            }

            $found++;
            if (! in_array('platform.admin', $route->gatherMiddleware(), true)) {
                $offenders[] = $uri;
            }
        }

        $offenders = array_values(array_unique($offenders));

        if ($offenders !== []) {
            return $this->signal('mutations_admin_only', self::STATUS_FAIL, 'Billing mutation route(s) not admin-guarded: '.implode(', ', $offenders));
        }

        return $found > 0
            ? $this->signal('mutations_admin_only', self::STATUS_PASS, "{$found} billing mutation route(s) are platform-admin only.")
            : $this->signal('mutations_admin_only', self::STATUS_WARN, 'No billing mutation routes registered.');
    }

    private function auditWiringSignal(): array
    {
        return class_exists(BillingAuditService::class)
            ? $this->signal('audit_wiring', self::STATUS_PASS, 'Billing audit service present.')
            : $this->signal('audit_wiring', self::STATUS_FAIL, 'Billing audit service missing.');
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
