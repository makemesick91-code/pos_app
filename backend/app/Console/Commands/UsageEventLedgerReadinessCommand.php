<?php

namespace App\Console\Commands;

use App\Services\UsageEventLedger\UsageEventLedgerReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 27 — usage-event-ledger:readiness. Reports whether the append-only usage
 * event ledger foundation is present and safe. Never prints secrets. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class UsageEventLedgerReadinessCommand extends Command
{
    protected $signature = 'usage-event-ledger:readiness {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Evaluate usage event ledger foundation readiness (GO/WATCH/NO-GO).';

    public function handle(UsageEventLedgerReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Usage Event Ledger Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === UsageEventLedgerReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === UsageEventLedgerReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
