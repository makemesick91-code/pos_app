<?php

namespace App\Console\Commands;

use App\Services\Pilot\DefectBurnDownService;
use Illuminate\Console\Command;

/**
 * Sprint 17 — pilot:burndown-summary.
 *
 * Prints the defect burn-down (total/closed/verified/open + by severity/status/
 * area + SLA breach + accepted risk) and the GO/WATCH/NO-GO decision. Read-only;
 * never prints secrets. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 —
 * NO-GO / strict WATCH.
 */
class PilotBurndownSummaryCommand extends Command
{
    protected $signature = 'pilot:burndown-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Report the pilot defect burn-down and emit a GO/WATCH/NO-GO decision.';

    public function handle(DefectBurnDownService $service): int
    {
        $summary = $service->summary();
        $counts = $summary['counts'];

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Defect Burn-down');
            $this->line('Total defects: '.$counts['total']);
            $this->line('Closed: '.$counts['closed']);
            $this->line('Verified: '.$counts['verified']);
            $this->line('Open: '.$counts['open']);
            $this->line('SLA breached (open): '.$counts['sla_breached_open']);
            $this->line('Accepted risk: '.$counts['accepted_risk']);
            $this->line('Decision: '.$summary['decision']);
        }

        return $this->exitCode($summary['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === DefectBurnDownService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === DefectBurnDownService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
