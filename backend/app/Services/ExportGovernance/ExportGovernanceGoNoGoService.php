<?php

namespace App\Services\ExportGovernance;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 29 — export governance GO / WATCH / NO_GO aggregation (EGC-R014).
 *
 * Combines the export governance enforcement audit, the Sprint 29 command
 * contract, the Sprint 25–28 prior gate contract, the Sprint 29 documentation
 * contract, and the persisted meterable check into a single decision. Never
 * prints secrets, never deploys, never charges, never mutates the ledger.
 */
class ExportGovernanceGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly ExportGovernanceAuditService $audit,
        private readonly ExportRouteRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $audit = $this->audit->evaluate();

        $signals = [
            $this->decisionSignal('export_governance_audit', (string) $audit['decision']),
            $this->ownCommandsSignal(),
            $this->priorGatesSignal(),
            $this->meterableSignal(),
            $this->docsSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'export_governance_audit' => $audit,
        ];
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('export_governance.export_governance_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('sprint29_commands', self::STATUS_PASS, count($required).' Sprint 29 commands registered.')
            : $this->signal('sprint29_commands', self::STATUS_FAIL, 'Missing Sprint 29 commands: '.implode(', ', $missing));
    }

    private function priorGatesSignal(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('export_governance.prior_sprint_gates', []);

        $missing = [];
        foreach ($gates as $commands) {
            foreach ((array) $commands as $command) {
                if (! in_array($command, $registered, true)) {
                    $missing[] = $command;
                }
            }
        }

        return $missing === []
            ? $this->signal('prior_sprint_gates', self::STATUS_PASS, 'Sprint 25/26/27/28 gate commands registered.')
            : $this->signal('prior_sprint_gates', self::STATUS_FAIL, 'Missing prior-sprint gate commands: '.implode(', ', $missing));
    }

    private function meterableSignal(): array
    {
        $meterKey = $this->registry->meterKey();
        $limits = (array) config('tenant_plan.usage_limits', []);

        return (bool) ($limits[$meterKey]['meterable'] ?? false)
            ? $this->signal('report_export_meter', self::STATUS_PASS, $meterKey.' remains meterable (EGC-R013).')
            : $this->signal('report_export_meter', self::STATUS_FAIL, $meterKey.' is no longer meterable.');
    }

    private function docsSignal(): array
    {
        $required = (array) config('export_governance.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('sprint29_docs', self::STATUS_PASS, count($required).' Sprint 29 docs present.')
            : $this->signal('sprint29_docs', self::STATUS_FAIL, 'Missing Sprint 29 docs: '.implode(', ', $missing));
    }

    private function decisionSignal(string $key, string $decision): array
    {
        return match ($decision) {
            self::DECISION_NO_GO => $this->signal($key, self::STATUS_FAIL, "{$key} is NO_GO."),
            self::DECISION_WATCH => $this->signal($key, self::STATUS_WARN, "{$key} is WATCH."),
            default => $this->signal($key, self::STATUS_PASS, "{$key} is GO."),
        };
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
