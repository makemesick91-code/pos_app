<?php

namespace App\Console\Commands;

use App\Services\Handover\ProductionHandoverGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 18 — production:handover-go-no-go.
 *
 * Aggregates the cumulative release/RC-UAT/deployment-field/monitoring-hypercare/
 * stabilization gate contract, the closure/handover documentation contract, the
 * final defect review, the final accepted-risk review, the latest pilot closure,
 * the latest production handover package, and its sign-off summary into a single
 * production handover GO/WATCH/NO_GO decision. Never prints secrets, never
 * deploys, never sends real alerts, never runs Android Gradle. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class ProductionHandoverGoNoGoCommand extends Command
{
    protected $signature = 'production:handover-go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate all pilot/release/handover gates into a production handover GO/WATCH/NO_GO decision.';

    public function handle(ProductionHandoverGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Handover GO/WATCH/NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Gates: '.json_encode($report['gates']));
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ProductionHandoverGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ProductionHandoverGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
