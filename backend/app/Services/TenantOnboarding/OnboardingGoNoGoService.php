<?php

namespace App\Services\TenantOnboarding;

use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantPaymentCollectionService;
use App\Services\Entitlements\EntitlementAccessService;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use App\Services\PaymentGateway\PaymentGatewaySettlementService;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 33 — the hard onboarding GO / WATCH / NO_GO gate (ONB-R026).
 *
 * Aggregates the governance audit, the Sprint 33 command self-contract, the
 * cumulative Sprint 24–32 prior-gate contract, the central-orchestrator wiring,
 * and the full commercial chain compatibility
 * (Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
 * Entitlement Runtime Access) into one decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a real
 * gateway, never marks paid, never lifts a suspension, never runs Android Gradle.
 */
class OnboardingGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly OnboardingGovernanceAuditService $governance,
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
                $this->orchestratorWiredSignal(),
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
            'onboarding_commands',
            (array) config('onboarding_governance.onboarding_commands', []),
            self::STATUS_FAIL,
            'Sprint 33 onboarding command(s) not registered: ',
        );
    }

    private function priorCommandsSignal(): array
    {
        return $this->commandsSignal(
            'prior_sprint_gates',
            (array) config('onboarding_governance.required_commands', []),
            self::STATUS_FAIL,
            'Sprint 24–32 prior gate command(s) not registered: ',
        );
    }

    private function commandsSignal(string $key, array $commands, string $failStatus, string $prefix): array
    {
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($commands, $registered));

        return [
            'key' => $key,
            'status' => $missing === [] ? self::STATUS_PASS : $failStatus,
            'message' => $missing === []
                ? 'All '.count($commands).' '.str_replace('_', ' ', $key).' registered.'
                : $prefix.implode(', ', $missing).'.',
        ];
    }

    private function orchestratorWiredSignal(): array
    {
        $ok = class_exists(TenantOnboardingService::class)
            && class_exists(TenantProvisioningService::class)
            && class_exists(TrialActivationService::class)
            && class_exists(FirstBranchProvisioningService::class)
            && class_exists(OwnerAdminProvisioningService::class)
            && class_exists(CashierProvisioningService::class)
            && class_exists(DeviceRegisterProvisioningService::class)
            && class_exists(TenantSeedDataService::class)
            && class_exists(OnboardingChecklistService::class);

        return [
            'key' => 'central_orchestrator_wired',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'TenantOnboardingService and all provisioning services are present.'
                : 'The central onboarding orchestrator wiring is incomplete.',
        ];
    }

    private function chainCompatibleSignal(): array
    {
        // Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
        // Entitlement Runtime Access. Onboarding builds on these services; it
        // never re-implements or bypasses them.
        $ok = class_exists(TenantPlanResolver::class)
            && class_exists(TenantInvoiceService::class)
            && class_exists(PaymentGatewayIntentService::class)
            && class_exists(PaymentGatewaySettlementService::class)
            && class_exists(TenantPaymentCollectionService::class)
            && class_exists(EntitlementAccessService::class);

        return [
            'key' => 'commercial_chain_compatible',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement chain is present.'
                : 'One or more commercial-chain services are missing.',
        ];
    }
}
