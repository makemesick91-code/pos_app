<?php

namespace App\Console\Commands;

use App\Services\UsageLedgerAnomaly\UsageLedgerGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 28 — usage-ledger:go-no-go. Aggregates anomaly detector + repair planner
 * wiring, repair governance (dry-run default + --apply/--reason/--actor), the
 * no-runtime-mutation-route guarantee, append-only guardrails, metadata redaction,
 * reports.exports.monthly meterability, the Sprint 25–27 prior gates, and the
 * Sprint 28 command/rules/docs contract into one GO/WATCH/NO-GO decision
 * (ULR-R015). Never prints secrets, never deploys, never mutates the ledger.
 * Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class UsageLedgerGoNoGoCommand extends Command
{
    protected $signature = 'usage-ledger:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate Sprint 28 usage-ledger anomaly & governed repair readiness into GO/WATCH/NO-GO.';

    public function handle(UsageLedgerGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Usage Ledger Anomaly & Governed Repair GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === UsageLedgerGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === UsageLedgerGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
