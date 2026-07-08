<?php

namespace App\Console\Commands;

use App\Services\SubscriptionRenewal\SubscriptionRenewalGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 24 — subscription-renewal:go-no-go.
 *
 * Aggregates the cumulative Sprint 13–23 gate contract, the renewal documentation
 * contract, the Android release readiness script, and the full subscription
 * renewal readiness evaluation into a single GO/WATCH/NO-GO decision. Never prints
 * secrets, never deploys, never charges, never calls a payment gateway, never
 * auto-suspends a tenant, never auto-renews, never sends real messages, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class SubscriptionRenewalGoNoGoCommand extends Command
{
    protected $signature = 'subscription-renewal:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate prior-sprint gates + subscription renewal readiness into a GO/WATCH/NO-GO decision.';

    public function handle(SubscriptionRenewalGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Subscription Renewal GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === SubscriptionRenewalGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === SubscriptionRenewalGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
