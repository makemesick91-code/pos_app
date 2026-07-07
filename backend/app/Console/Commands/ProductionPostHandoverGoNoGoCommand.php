<?php

namespace App\Console\Commands;

use App\Services\Operations\PostHandoverGovernanceReportService;
use Illuminate\Console\Command;

/**
 * Sprint 19 — production:post-handover-go-no-go.
 *
 * Aggregates the cumulative release/RC-UAT/deployment-field/monitoring-hypercare/
 * stabilization/closure-handover gate contract, the operations documentation
 * contract, the production operations health, the incident summary, the
 * backup/restore governance, the support/SLA governance, the maintenance window
 * governance, and the release/rollback governance into a single post-handover
 * production operations GO/WATCH/NO_GO decision. Never prints secrets, never
 * deploys, never runs real backup/restore, never sends real alerts, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO
 * / strict WATCH.
 */
class ProductionPostHandoverGoNoGoCommand extends Command
{
    protected $signature = 'production:post-handover-go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate all gates + operations governance into a post-handover GO/WATCH/NO-GO decision.';

    public function handle(PostHandoverGovernanceReportService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Post-Handover Production Operations GO/WATCH/NO-GO');
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
        if ($decision === PostHandoverGovernanceReportService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PostHandoverGovernanceReportService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
