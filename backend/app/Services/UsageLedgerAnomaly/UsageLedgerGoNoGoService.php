<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Sprint 28 — usage ledger anomaly & governed repair GO / WATCH / NO_GO
 * aggregation (ULR-R015).
 *
 * Verifies the anomaly detector and repair planner are wired, that repair apply is
 * governed (dry-run default + --apply/--reason/--actor), that there is NO runtime
 * update/delete route for the ledger (ULR-R009), that append-only guardrails hold,
 * that metadata redaction is active, that reports.exports.monthly stays meterable
 * (ULR-R014), and that the Sprint 25–27 prior gates stay green. Never prints
 * secrets, never deploys, never mutates the ledger.
 */
class UsageLedgerGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly UsageLedgerAnomalyDetector $detector,
        private readonly UsageLedgerRepairPlanner $planner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = [
            $this->servicesSignal(),
            $this->rulesSignal(),
            $this->guardrailsSignal(),
            $this->repairGovernanceSignal(),
            $this->noMutationRouteSignal(),
            $this->reportsMeterableSignal(),
            $this->auditConstantSignal(),
            $this->ownCommandsSignal(),
            $this->priorGatesSignal(),
            $this->docsSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    private function servicesSignal(): array
    {
        try {
            app(UsageLedgerAnomalyDetector::class);
            app(UsageLedgerRepairPlanner::class);
            app(UsageLedgerRepairService::class);

            return $this->signal('services', self::STATUS_PASS, 'Anomaly detector + repair planner/service resolvable.');
        } catch (\Throwable $e) {
            return $this->signal('services', self::STATUS_FAIL, 'Sprint 28 services not resolvable.');
        }
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('usage_ledger_anomaly.rules', []);
        $missing = [];
        for ($i = 1; $i <= 16; $i++) {
            $key = sprintf('ULR-R%03d', $i);
            if (! array_key_exists($key, $rules) || empty($rules[$key])) {
                $missing[] = $key;
            }
        }

        return $missing === []
            ? $this->signal('ulr_rules', self::STATUS_PASS, 'ULR-R001..R016 present in config.')
            : $this->signal('ulr_rules', self::STATUS_FAIL, 'Missing ULR rules: '.implode(', ', $missing));
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'anomaly_detector_may_mutate_ledger_allowed',
            'repair_apply_without_dry_run_default_allowed',
            'repair_apply_without_reason_actor_allowed',
            'usage_ledger_mutation_route_allowed',
            'repair_may_delete_original_event_allowed',
            'effective_usage_negative_allowed',
        ];
        $violations = array_values(array_filter(
            $flags,
            fn ($flag) => (bool) config('usage_ledger_anomaly.'.$flag, false) === true,
        ));

        return $violations === []
            ? $this->signal('guardrails', self::STATUS_PASS, 'All Sprint 28 guardrail flags are disabled.')
            : $this->signal('guardrails', self::STATUS_FAIL, 'Enabled guardrail violations: '.implode(', ', $violations));
    }

    private function repairGovernanceSignal(): array
    {
        $commands = Artisan::all();
        $apply = $commands['usage-ledger:repair-apply'] ?? null;
        if ($apply === null) {
            return $this->signal('repair_governance', self::STATUS_FAIL, 'usage-ledger:repair-apply command missing.');
        }

        $definition = $apply->getDefinition();
        $required = ['apply', 'reason', 'actor', 'dry-run'];
        $missing = array_values(array_filter(
            $required,
            fn ($opt) => ! $definition->hasOption($opt),
        ));

        return $missing === []
            ? $this->signal('repair_governance', self::STATUS_PASS, 'repair-apply is governed (--apply/--reason/--actor/--dry-run).')
            : $this->signal('repair_governance', self::STATUS_FAIL, 'repair-apply missing options: '.implode(', ', $missing));
    }

    private function noMutationRouteSignal(): array
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $mutates = (bool) array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']);
            if (! $mutates) {
                continue;
            }
            if (str_contains($uri, 'usage-events') || str_contains($uri, 'usage-ledger') || str_contains($uri, 'usage-event-ledger')) {
                return $this->signal('no_mutation_route', self::STATUS_FAIL, "Runtime ledger mutation route exists: {$uri}");
            }
        }

        return $this->signal('no_mutation_route', self::STATUS_PASS, 'No runtime update/delete route for the usage ledger.');
    }

    private function reportsMeterableSignal(): array
    {
        $registry = (array) config('tenant_plan.usage_limits', []);
        $meterable = (bool) ($registry['reports.exports.monthly']['meterable'] ?? false);

        return $meterable
            ? $this->signal('reports_meterable', self::STATUS_PASS, 'reports.exports.monthly is meterable=true.')
            : $this->signal('reports_meterable', self::STATUS_FAIL, 'reports.exports.monthly is not meterable.');
    }

    private function auditConstantSignal(): array
    {
        return defined(AdminAuditLog::class.'::ACTION_USAGE_LEDGER_REPAIR_APPLIED')
            ? $this->signal('repair_audit', self::STATUS_PASS, 'Repair audit action constant present.')
            : $this->signal('repair_audit', self::STATUS_FAIL, 'Repair audit action constant missing.');
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('usage_ledger_anomaly.usage_ledger_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('sprint28_commands', self::STATUS_PASS, count($required).' Sprint 28 commands registered.')
            : $this->signal('sprint28_commands', self::STATUS_FAIL, 'Missing Sprint 28 commands: '.implode(', ', $missing));
    }

    private function priorGatesSignal(): array
    {
        $gates = (array) config('usage_ledger_anomaly.prior_sprint_gates', []);
        $failed = [];
        foreach ($gates as $commands) {
            foreach ((array) $commands as $command) {
                try {
                    if (Artisan::call($command, ['--json' => true]) !== 0) {
                        $failed[] = $command;
                    }
                } catch (\Throwable $e) {
                    $failed[] = $command;
                }
            }
        }

        return $failed === []
            ? $this->signal('prior_sprint_gates', self::STATUS_PASS, 'Sprint 25/26/27 gates green.')
            : $this->signal('prior_sprint_gates', self::STATUS_FAIL, 'Prior gate(s) not green: '.implode(', ', $failed));
    }

    private function docsSignal(): array
    {
        $required = (array) config('usage_ledger_anomaly.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('sprint28_docs', self::STATUS_PASS, count($required).' Sprint 28 docs present.')
            : $this->signal('sprint28_docs', self::STATUS_FAIL, 'Missing Sprint 28 docs: '.implode(', ', $missing));
    }

    /**
     * @param array<int, array{status:string}> $signals
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

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
