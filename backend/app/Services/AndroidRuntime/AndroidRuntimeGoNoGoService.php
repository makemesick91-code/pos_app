<?php

namespace App\Services\AndroidRuntime;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 34 — the hard Android runtime GO / WATCH / NO_GO gate (ADR-R030).
 *
 * Aggregates the governance audit, the Sprint 34 command self-contract, the
 * cumulative Sprint 24–33 prior-gate contract, the runtime-service wiring and the
 * full commercial-chain compatibility
 * (Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
 * Entitlement Runtime Access → Tenant Onboarding → Android Runtime) into one
 * decision. Never prints secrets, never deploys, never charges, never marks paid,
 * never lifts a suspension, never runs Android Gradle.
 */
class AndroidRuntimeGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly AndroidRuntimeGovernanceAuditService $governance,
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
                $this->runtimeWiredSignal(),
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
            'android_runtime_commands',
            (array) config('android_runtime_governance.android_runtime_commands', []),
            'Sprint 34 android runtime command(s) not registered: ',
        );
    }

    private function priorCommandsSignal(): array
    {
        return $this->commandsSignal(
            'prior_sprint_gates',
            (array) config('android_runtime_governance.required_commands', []),
            'Sprint 24–33 prior gate command(s) not registered: ',
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

    private function runtimeWiredSignal(): array
    {
        $ok = class_exists(AndroidRuntimeAccessService::class)
            && class_exists(DeviceActivationService::class)
            && class_exists(DeviceRevocationService::class)
            && class_exists(CashierRuntimeSessionService::class)
            && class_exists(AndroidOfflinePolicyService::class)
            && class_exists(AndroidSyncIngestionService::class)
            && class_exists(AndroidSyncConflictService::class)
            && class_exists(AndroidSyncRedactor::class)
            && class_exists(AndroidRuntimeSummaryService::class);

        return [
            'key' => 'runtime_services_wired',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'AndroidRuntimeAccessService and all runtime services are present.'
                : 'One or more Android runtime services are missing.',
        ];
    }

    private function chainCompatibleSignal(): array
    {
        $ok = class_exists(\App\Services\Entitlements\EntitlementAccessService::class)
            && class_exists(\App\Services\Billing\TenantInvoiceService::class)
            && class_exists(\App\Services\PaymentGateway\PaymentGatewayIntentService::class)
            && class_exists(\App\Services\PaymentGateway\PaymentGatewaySettlementService::class)
            && class_exists(\App\Services\Billing\TenantPaymentCollectionService::class)
            && class_exists(\App\Services\TenantOnboarding\TenantOnboardingService::class);

        return [
            'key' => 'commercial_chain_compatible',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement → Onboarding → Android Runtime chain is wired.'
                : 'One or more upstream commercial-chain services are missing.',
        ];
    }
}
