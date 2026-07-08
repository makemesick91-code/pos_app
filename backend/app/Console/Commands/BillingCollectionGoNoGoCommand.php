<?php

namespace App\Console\Commands;

use App\Services\BillingCollection\BillingCollectionGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 23 — billing-collection:go-no-go.
 *
 * Aggregates the cumulative Sprint 13–22 gate contract, the billing collection
 * documentation contract, the Android release readiness script, and the full
 * billing collection readiness evaluation into a single GO/WATCH/NO-GO decision.
 * Never prints secrets, never deploys, never charges, never calls a payment
 * gateway, never auto-suspends a tenant, never sends real messages, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class BillingCollectionGoNoGoCommand extends Command
{
    protected $signature = 'billing-collection:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate prior-sprint gates + billing collection readiness into a GO/WATCH/NO-GO decision.';

    public function handle(BillingCollectionGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing Collection GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === BillingCollectionGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === BillingCollectionGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
