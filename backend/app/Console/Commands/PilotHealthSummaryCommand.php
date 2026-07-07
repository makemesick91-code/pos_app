<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotHealthSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 16 — pilot:health-summary.
 *
 * Aggregates the canonical pilot health areas into PASS/WARN/FAIL counts and a
 * GO / WATCH / NO-GO decision. Reads an optional structured monitoring result
 * file (demo/placeholder data only). Never mutates production data, never sends
 * real alerts, and never prints secrets. Exit code: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotHealthSummaryCommand extends Command
{
    protected $signature = 'pilot:health-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarise canonical pilot health areas into a GO/WATCH/NO-GO decision.';

    public function handle(PilotHealthSummaryService $service): int
    {
        $summary = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Health Summary');
            $this->line("Areas: {$summary['total_areas']}");
            $this->line("PASS: {$summary['counts']['PASS']}");
            $this->line("WARN: {$summary['counts']['WARN']}");
            $this->line("FAIL: {$summary['counts']['FAIL']}");
            $this->line("Decision: {$summary['decision']}");
        }

        return $this->exitCode($summary['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotHealthSummaryService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotHealthSummaryService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
