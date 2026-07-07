<?php

namespace App\Services\Operations;

use App\Models\ProductionOperationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 19 — post-handover production operations GO / WATCH / NO_GO aggregation.
 *
 * Combines the cumulative prior-sprint gate contract (Sprint 13 release, 14
 * RC/UAT, 15 deployment/field, 16 monitoring/hypercare, 17 stabilization, 18
 * closure/handover commands registered), the operations documentation contract,
 * the production operations health evaluation, the incident summary, the
 * backup/restore governance, the support/SLA governance, the maintenance window
 * governance, and the release/rollback governance into a single decision.
 *
 *   NO_GO — any blocking signal fails, a required command/doc is missing, an
 *           open P0/P1 without a valid accepted risk, an SLA-breached P0/P1, an
 *           expired blocking accepted risk, or a HIGH/CRITICAL maintenance
 *           window without a rollback plan.
 *   WATCH — no blocking failure but a warning exists (open P2 with mitigation,
 *           non-critical health warning, planned high-risk maintenance with a
 *           rollback plan, or an approved/accepted risk).
 *   GO    — every signal passes.
 *
 * Never prints secrets, never deploys, never runs real backup/restore, never
 * sends real alerts, never runs Android Gradle.
 */
class PostHandoverGovernanceReportService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly ProductionOperationsHealthService $health,
        private readonly ProductionIncidentService $incidents,
        private readonly BackupRestoreGovernanceService $backup,
        private readonly SupportSlaGovernanceService $supportSla,
        private readonly MaintenanceWindowService $maintenance,
        private readonly ReleaseRollbackGovernanceService $releaseRollback,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $health = $this->health->evaluate($now);
        $incidentSummary = $this->incidents->summary($now);
        $backup = $this->backup->evaluate();
        $supportSla = $this->supportSla->evaluate($now);
        $maintenance = $this->maintenance->summary();
        $releaseRollback = $this->releaseRollback->evaluate();

        $signals = [
            $this->commandsSignal(),
            $this->docsSignal(),
            $this->androidScriptSignal(),
            $this->decisionSignal('production_health', (string) $health['decision']),
            $this->decisionSignal('incident_governance', (string) $incidentSummary['decision']),
            $this->decisionSignal('backup_restore_governance', (string) $backup['decision']),
            $this->decisionSignal('support_sla_governance', (string) $supportSla['decision']),
            $this->decisionSignal('maintenance_governance', (string) $maintenance['decision']),
            $this->decisionSignal('release_rollback_governance', (string) $releaseRollback['decision']),
        ];

        $latestRun = ProductionOperationRun::query()->latest('id')->first();

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'production_health' => $health,
            'incident_summary' => $incidentSummary,
            'backup_restore_governance' => $backup,
            'support_sla_governance' => $supportSla,
            'maintenance_governance' => $maintenance,
            'release_rollback_governance' => $releaseRollback,
            'latest_operation_run' => $latestRun === null ? null : [
                'reference' => $latestRun->operation_reference,
                'status' => $latestRun->status,
                'decision' => $latestRun->decision,
            ],
        ];
    }

    /**
     * @param array<int,array{status:string}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_WARN) {
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

    private function decisionSignal(string $key, string $decision): array
    {
        return match ($decision) {
            self::DECISION_NO_GO => $this->signal($key, self::STATUS_FAIL, "{$key} is NO_GO."),
            self::DECISION_WATCH => $this->signal($key, self::STATUS_WARN, "{$key} is WATCH."),
            default => $this->signal($key, self::STATUS_PASS, "{$key} is GO."),
        };
    }

    private function commandsSignal(): array
    {
        $required = (array) config('production_operations.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' release/pilot/handover/operations commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('production_operations.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('operations_docs', self::STATUS_PASS, count($required).' operations docs present.')
            : $this->signal('operations_docs', self::STATUS_FAIL, 'Missing operations docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('production_operations.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    /**
     * @return array<string,bool>
     */
    private function gateReferences(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = [
            'release_gate' => ['production:readiness-check', 'release:go-no-go'],
            'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
            'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
            'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
            'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
            'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
            'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
        ];

        $out = [];
        foreach ($gates as $name => $commands) {
            $out[$name] = array_diff($commands, $registered) === [];
        }

        return $out;
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
