<?php

namespace App\Console\Commands;

use App\Services\SubscriptionRenewal\SubscriptionRenewalReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 24 — subscription-renewal:readiness.
 *
 * Evaluates the automation guardrails, renewal docs, default policy governance,
 * run/candidate governance, manual-only dunning governance, decision governance,
 * risk review and sign-off review into a GO/WATCH/NO-GO decision. Never prints
 * secrets, never charges, never sends a real message. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO_GO.
 */
class SubscriptionRenewalReadinessCommand extends Command
{
    protected $signature = 'subscription-renewal:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate subscription renewal & dunning readiness into a GO/WATCH/NO-GO decision.';

    public function handle(SubscriptionRenewalReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Subscription Renewal Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === SubscriptionRenewalReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === SubscriptionRenewalReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
