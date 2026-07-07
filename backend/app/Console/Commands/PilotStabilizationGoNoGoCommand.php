<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotStabilizationReportService;
use Illuminate\Console\Command;

/**
 * Sprint 17 — pilot:stabilization-go-no-go.
 *
 * Aggregates the defect burn-down, SLA breach summary, accepted-risk summary,
 * fix-verification/retest state, stabilization docs, the cumulative release/pilot
 * command contract, and the Android release readiness script into a single
 * GO / WATCH / NO-GO stabilization decision. Never prints secrets, never mutates
 * production data, never sends real alerts, never runs Android Gradle. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO-GO / strict WATCH.
 */
class PilotStabilizationGoNoGoCommand extends Command
{
    protected $signature = 'pilot:stabilization-go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate the pilot stabilization gates into a GO/WATCH/NO-GO decision.';

    public function handle(PilotStabilizationReportService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot Stabilization GO/WATCH/NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Gates: '.json_encode($report['gates']));
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode($report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PilotStabilizationReportService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PilotStabilizationReportService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
