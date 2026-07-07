<?php

namespace App\Console\Commands;

use App\Services\Commercial\CommercialLaunchGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 20 — commercial:launch-go-no-go.
 *
 * Aggregates the cumulative release/RC-UAT/deployment-field/monitoring-hypercare/
 * stabilization/closure-handover/production-operations gate contract, the
 * commercial documentation contract, the Android release readiness script, and
 * the full commercial launch readiness (package catalog, pricing governance, sales
 * enablement, onboarding capacity, risk review, launch signoff) into a single
 * commercial launch GO/WATCH/NO-GO decision. Never prints secrets, never deploys,
 * never bills a real customer, never opens public signup, never sends real alerts,
 * never runs Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH),
 * 1 — NO_GO / strict WATCH.
 */
class CommercialLaunchGoNoGoCommand extends Command
{
    protected $signature = 'commercial:launch-go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate all prior gates + commercial readiness into a commercial launch GO/WATCH/NO-GO decision.';

    public function handle(CommercialLaunchGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Commercial Launch GO/WATCH/NO-GO');
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
        if ($decision === CommercialLaunchGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === CommercialLaunchGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
