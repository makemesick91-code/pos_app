<?php

namespace App\Console\Commands;

use App\Services\PublicWebsite\PublicWebsiteGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 21 — public-website:go-no-go.
 *
 * Aggregates the cumulative release/RC-UAT/deployment-field/monitoring-hypercare/
 * stabilization/closure-handover/production-operations/commercial-launch gate
 * contract, the public website documentation contract, the Android release
 * readiness script, and the full public website readiness (pages, landing content,
 * lead governance, SEO metadata, privacy/cookie, risk review, signoff review) into
 * a single public website GO/WATCH/NO-GO decision. Never prints secrets, never
 * deploys, never bills a real customer, never opens public signup, never sends
 * real alerts, never runs Android Gradle. Exit code: 0 — GO/WATCH (unless --strict
 * on WATCH), 1 — NO_GO / strict WATCH.
 */
class PublicWebsiteGoNoGoCommand extends Command
{
    protected $signature = 'public-website:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate all prior gates + public website readiness into a GO/WATCH/NO-GO decision.';

    public function handle(PublicWebsiteGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Public Website GO/WATCH/NO-GO');
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
        if ($decision === PublicWebsiteGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PublicWebsiteGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
