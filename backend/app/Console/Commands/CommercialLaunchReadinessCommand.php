<?php

namespace App\Console\Commands;

use App\Services\Commercial\CommercialLaunchReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 20 — commercial:launch-readiness.
 *
 * Aggregates package catalog readiness, pricing/plan governance, sales enablement
 * readiness, onboarding capacity, commercial risk review, launch signoff review,
 * and the commercial documentation contract into a single GO/WATCH/NO-GO
 * decision. Never prints secrets, never deploys, never bills a real customer,
 * never opens public signup, never sends real alerts. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class CommercialLaunchReadinessCommand extends Command
{
    protected $signature = 'commercial:launch-readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate commercial launch readiness (package/pricing/sales/onboarding/risk/signoff) into a GO/WATCH/NO-GO decision.';

    public function handle(CommercialLaunchReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Commercial Launch Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === CommercialLaunchReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === CommercialLaunchReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
