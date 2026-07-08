<?php

namespace App\Console\Commands;

use App\Services\BillingCollection\BillingCollectionReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 23 — billing-collection:readiness.
 *
 * Evaluates billing collection readiness (config guardrails, docs, billing
 * accounts, cycles, invoice lifecycle, payment evidence, manual collection, risk
 * governance, sign-off governance) into a secret-safe PASS/WARN/FAIL report and a
 * GO/WATCH/NO-GO decision. Never prints secrets, never charges, never calls a
 * payment gateway, never auto-suspends a tenant, never sends real messages, never
 * runs Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 —
 * NO_GO.
 */
class BillingCollectionReadinessCommand extends Command
{
    protected $signature = 'billing-collection:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate SaaS billing collection readiness into a GO/WATCH/NO-GO decision.';

    public function handle(BillingCollectionReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing Collection Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === BillingCollectionReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === BillingCollectionReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
