<?php

namespace App\Services\UsageEventLedger;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 27 — report export metering & usage event ledger GO / WATCH / NO_GO
 * aggregation (UEL-R014).
 *
 * Combines the usage event ledger readiness, the report export metering
 * enforcement audit, the Sprint 27 command contract, the Sprint 24–26 prior gate
 * contract, and the Sprint 27 documentation contract into a single decision.
 * Never prints secrets, never deploys, never charges, never auto-suspends a
 * tenant, never mutates the ledger.
 */
class ReportExportMeteringGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly UsageEventLedgerReadinessService $readiness,
        private readonly ReportExportMeteringEnforcementAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $readiness = $this->readiness->evaluate();
        $audit = $this->audit->evaluate();

        $signals = [
            $this->decisionSignal('usage_event_ledger_readiness', (string) $readiness['decision']),
            $this->decisionSignal('report_export_enforcement_audit', (string) $audit['decision']),
            $this->ownCommandsSignal(),
            $this->priorGatesSignal(),
            $this->docsSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'usage_event_ledger_readiness' => $readiness,
            'report_export_enforcement_audit' => $audit,
        ];
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('usage_event_ledger.usage_event_ledger_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('sprint27_commands', self::STATUS_PASS, count($required).' Sprint 27 commands registered.')
            : $this->signal('sprint27_commands', self::STATUS_FAIL, 'Missing Sprint 27 commands: '.implode(', ', $missing));
    }

    private function priorGatesSignal(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('usage_event_ledger.prior_sprint_gates', []);

        $missing = [];
        foreach ($gates as $commands) {
            foreach ((array) $commands as $command) {
                if (! in_array($command, $registered, true)) {
                    $missing[] = $command;
                }
            }
        }

        return $missing === []
            ? $this->signal('prior_sprint_gates', self::STATUS_PASS, 'Sprint 24/25/26 gate commands registered.')
            : $this->signal('prior_sprint_gates', self::STATUS_FAIL, 'Missing prior-sprint gate commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('usage_event_ledger.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('sprint27_docs', self::STATUS_PASS, count($required).' Sprint 27 docs present.')
            : $this->signal('sprint27_docs', self::STATUS_FAIL, 'Missing Sprint 27 docs: '.implode(', ', $missing));
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
