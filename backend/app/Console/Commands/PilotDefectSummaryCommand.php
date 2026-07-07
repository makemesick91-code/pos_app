<?php

namespace App\Console\Commands;

use App\Services\Pilot\DefectBurnDownService;
use Illuminate\Console\Command;

/**
 * Sprint 17 — pilot:defect-summary.
 *
 * Prints the open defect counts by severity and the resulting GO/WATCH/NO-GO
 * decision. Read-only; never prints secrets. Exit code: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotDefectSummaryCommand extends Command
{
    protected $signature = 'pilot:defect-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarise pilot defects by severity and emit a GO/WATCH/NO-GO decision.';

    public function handle(DefectBurnDownService $service): int
    {
        $summary = $service->summary();
        $bySeverity = $summary['by_severity'];
        $counts = $summary['counts'];

        $report = [
            'decision' => $summary['decision'],
            'open' => $counts['open'],
            'open_blocking' => $counts['open_blocking'],
            'open_major' => $counts['open_major'],
            'by_severity' => $bySeverity,
            'accepted_risk' => $counts['accepted_risk'],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Defect Summary');
            $this->line('Open BLOCKER: '.($this->openBy($service, 'BLOCKER')));
            $this->line('Open CRITICAL: '.($this->openBy($service, 'CRITICAL')));
            $this->line('Open MAJOR: '.$counts['open_major']);
            $this->line('Accepted risk: '.$counts['accepted_risk']);
            $this->line('Decision: '.$summary['decision']);
        }

        return $this->exitCode($summary['decision']);
    }

    private function openBy(DefectBurnDownService $service, string $severity): int
    {
        return \App\Models\PilotDefect::query()->open()->withSeverity($severity)->count();
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
