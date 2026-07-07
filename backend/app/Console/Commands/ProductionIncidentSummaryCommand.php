<?php

namespace App\Console\Commands;

use App\Services\Operations\ProductionIncidentService;
use Illuminate\Console\Command;

/**
 * Sprint 19 — production:incident-summary.
 *
 * Aggregates open production incidents by severity/status/SLA into a
 * GO/WATCH/NO_GO decision. Open P0/P1 without a valid accepted risk force NO_GO;
 * open P2 forces WATCH. Never prints secrets, never sends real alerts. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class ProductionIncidentSummaryCommand extends Command
{
    protected $signature = 'production:incident-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize open production incidents into a GO/WATCH/NO-GO decision.';

    public function handle(ProductionIncidentService $service): int
    {
        $report = $service->summary();
        $counts = $report['counts'];
        $bySeverity = $counts['open_by_severity'];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Incident Summary');
            $this->line('Open P0: '.($bySeverity['P0'] ?? 0));
            $this->line('Open P1: '.($bySeverity['P1'] ?? 0));
            $this->line('Open P2: '.($bySeverity['P2'] ?? 0));
            $this->line('SLA breached: '.$counts['sla_breached']);
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ProductionIncidentService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ProductionIncidentService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
