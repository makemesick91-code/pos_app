<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 36 — the hard observability GO / WATCH / NO_GO gate (OBS-R032).
 *
 * Aggregates the governance audit, the Sprint 36 command self-contract, the
 * cumulative Sprint 24–35 prior-gate contract, the observability-service wiring
 * and the full commercial-chain compatibility
 * (Plan → Invoice → Payment Intent → Gateway Settlement → Collection →
 * Entitlement Runtime Access → Tenant Onboarding → Android Runtime → Support
 * Operations → Observability) into one decision. Never prints secrets, never
 * deploys, never charges, never marks paid, never lifts a suspension, never runs
 * Android Gradle.
 */
class ObservabilityGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly ObservabilityGovernanceAuditService $governance,
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
                $this->servicesWiredSignal(),
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
            'observability_commands',
            (array) config('observability_governance.observability_commands', []),
            'Sprint 36 observability command(s) not registered: ',
        );
    }

    private function priorCommandsSignal(): array
    {
        return $this->commandsSignal(
            'prior_sprint_gates',
            (array) config('observability_governance.required_commands', []),
            'Sprint 24–35 prior gate command(s) not registered: ',
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

    private function servicesWiredSignal(): array
    {
        $ok = class_exists(ObservabilityHealthService::class)
            && class_exists(InfrastructureHealthCheckService::class)
            && class_exists(QueueHealthService::class)
            && class_exists(FailedJobDiagnosticsService::class)
            && class_exists(QueueActionService::class)
            && class_exists(SchedulerHealthService::class)
            && class_exists(TenantRuntimeProbeService::class)
            && class_exists(ObservabilityAnomalyScanService::class)
            && class_exists(ObservabilityIncidentSuggestionService::class)
            && class_exists(ObservabilityMetricsService::class)
            && class_exists(ObservabilityAuditService::class)
            && class_exists(ObservabilityRedactor::class);

        return [
            'key' => 'observability_services_wired',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'All observability services are present.'
                : 'One or more observability services are missing.',
        ];
    }

    private function chainCompatibleSignal(): array
    {
        $ok = class_exists(\App\Services\Entitlements\EntitlementAccessService::class)
            && class_exists(\App\Services\Billing\TenantInvoiceService::class)
            && class_exists(\App\Services\PaymentGateway\PaymentGatewaySettlementService::class)
            && class_exists(\App\Services\Billing\TenantPaymentCollectionService::class)
            && class_exists(\App\Services\TenantOnboarding\TenantOnboardingService::class)
            && class_exists(\App\Services\AndroidRuntime\AndroidRuntimeAccessService::class)
            && class_exists(\App\Services\SupportOperations\SupportTenantHealthService::class)
            && class_exists(\App\Services\SupportOperations\SupportIncidentService::class);

        return [
            'key' => 'commercial_chain_compatible',
            'status' => $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            'message' => $ok
                ? 'Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement → Onboarding → Android Runtime → Support Operations → Observability chain is wired.'
                : 'One or more upstream commercial-chain services are missing.',
        ];
    }
}
