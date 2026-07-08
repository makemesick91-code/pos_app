<?php

namespace App\Console\Commands;

use App\Services\SalesPipeline\SalesPipelineGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 22 — sales-pipeline:go-no-go.
 *
 * Aggregates the cumulative Sprint 13–21 gate contract, the sales pipeline
 * documentation contract, the Android release readiness script, and the full
 * sales pipeline readiness evaluation into a single GO/WATCH/NO-GO decision. Never
 * prints secrets, never deploys, never bills, never creates a tenant/user/
 * subscription/device from a lead, never integrates a real CRM, never sends real
 * messages, never runs Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on
 * WATCH), 1 — NO_GO.
 */
class SalesPipelineGoNoGoCommand extends Command
{
    protected $signature = 'sales-pipeline:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate prior-sprint gates + sales pipeline readiness into a GO/WATCH/NO-GO decision.';

    public function handle(SalesPipelineGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Sales Pipeline GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === SalesPipelineGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === SalesPipelineGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
