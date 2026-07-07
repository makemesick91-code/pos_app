<?php

namespace App\Console\Commands;

use App\Services\Handover\ProductionHandoverService;
use Illuminate\Console\Command;

/**
 * Sprint 18 — production:handover-summary.
 *
 * Evaluates the production handover documentation/readiness contract (handover
 * pack, operator/admin handover, support/SLA handover, backup/restore handover,
 * ownership matrix) into a GO/WATCH/NO_GO decision. Read-only — never persists,
 * never prints secrets, never deploys. Exit code: 0 — GO/WATCH (unless --strict
 * on WATCH), 1 — NO_GO / strict WATCH.
 */
class ProductionHandoverSummaryCommand extends Command
{
    protected $signature = 'production:handover-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize the production handover package readiness into a GO/WATCH/NO_GO decision.';

    public function handle(ProductionHandoverService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Handover Summary');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ProductionHandoverService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ProductionHandoverService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
