<?php

namespace App\Services\SupportOperations;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 35 — the hard support-operations GO / WATCH / NO_GO gate (SUP-R030).
 *
 * Aggregates the governance audit, the Sprint 35 command self-contract, the
 * cumulative Sprint 24–34 prior-gate contract, the support-service wiring and the
 * full commercial-chain compatibility
 * (Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
 * Entitlement Runtime Access → Tenant Onboarding → Android Runtime → Support
 * Operations) into one decision. Never prints secrets, never deploys, never
 * charges, never marks paid, never lifts a suspension, never runs Android Gradle.
 */
class SupportGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly SupportGovernanceAuditService $governance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = array_merge(
            $this->governance->evaluate(),
            [
                $this->ownCommandsSignal(),
                $this->priorCommandsSignal(),
                $this->supportWiredSignal(),
                $this->chainCompatibleSignal(),
            ],
        );

        $decision = self::DECISION_GO;
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                $decision = self::DECISION_NO_GO;
                break;
            }
            if ($signal['status'] === self::STATUS_WARN && $decision === self::DECISION_GO) {
                $decision = self::DECISION_WATCH;
            }
        }

        return ['decision' => $decision, 'signals' => $signals];
    }

    private function ownCommandsSignal(): array
    {
        return $this->commandsSignal(
            'support_ops_commands',
            (array) config('support_operations_governance.support_ops_commands', []),
            'Sprint 35 support-ops command(s) not registered: ',
        );
    }

    private function priorCommandsSignal(): array
    {
        return $this->commandsSignal(
            'prior_sprint_gates',
            (array) config('support_operations_governance.required_commands', []),
            'Sprint 24–34 prior gate command(s) not registered: ',
        );
    }

    private function commandsSignal(string $key, array $commands, string $prefix): array
    {
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($commands, $registered));

        return [
            'key' => $key,
            'status' => $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $missing === []
                ? 'All '.count($commands).' '.str_replace('_', ' ', $key).' registered.'
                : $prefix.implode(', ', $missing).'.',
        ];
    }

    private function supportWiredSignal(): array
    {
        $ok = class_exists(SupportTenantHealthService::class)
            && class_exists(SupportDiagnosticTimelineService::class)
            && class_exists(SupportBillingViewerService::class)
            && class_exists(SupportPaymentViewerService::class)
            && class_exists(SupportEntitlementViewerService::class)
            && class_exists(SupportOnboardingViewerService::class)
            && class_exists(SupportAndroidRuntimeViewerService::class)
            && class_exists(SupportDeviceOperationsService::class)
            && class_exists(SupportIncidentService::class)
            && class_exists(SupportReadOnlyContextService::class)
            && class_exists(SupportImpersonationService::class)
            && class_exists(SupportAuditService::class)
            && class_exists(SupportRedactor::class);

        return [
            'key' => 'support_services_wired',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'All support-operations services are present.'
                : 'One or more support-operations services are missing.',
        ];
    }

    private function chainCompatibleSignal(): array
    {
        $ok = class_exists(\App\Services\Entitlements\EntitlementAccessService::class)
            && class_exists(\App\Services\Billing\TenantInvoiceService::class)
            && class_exists(\App\Services\PaymentGateway\PaymentGatewayIntentService::class)
            && class_exists(\App\Services\PaymentGateway\PaymentGatewaySettlementService::class)
            && class_exists(\App\Services\Billing\TenantPaymentCollectionService::class)
            && class_exists(\App\Services\TenantOnboarding\TenantOnboardingService::class)
            && class_exists(\App\Services\AndroidRuntime\AndroidRuntimeAccessService::class)
            && class_exists(\App\Services\AndroidRuntime\DeviceRevocationService::class);

        return [
            'key' => 'commercial_chain_compatible',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement → Onboarding → Android Runtime → Support Operations chain is wired.'
                : 'One or more upstream commercial-chain services are missing.',
        ];
    }
}
