<?php

namespace App\Console\Commands;

use App\Services\SalesPipeline\SalesPipelineReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 22 — sales-pipeline:readiness.
 *
 * Evaluates sales pipeline readiness (canonical stages, lead intake, assignment
 * governance, activity tracking, qualification, onboarding handover readiness,
 * risk governance, signoff governance, docs) into a secret-safe PASS/WARN/FAIL
 * report and a GO/WATCH/NO-GO decision. Never prints secrets, never deploys, never
 * bills, never creates a tenant/user/subscription/device, never sends real
 * messages, never runs Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on
 * WATCH), 1 — NO_GO.
 */
class SalesPipelineReadinessCommand extends Command
{
    protected $signature = 'sales-pipeline:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate lead management / sales pipeline readiness into a GO/WATCH/NO-GO decision.';

    public function handle(SalesPipelineReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Sales Pipeline Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === SalesPipelineReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === SalesPipelineReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
