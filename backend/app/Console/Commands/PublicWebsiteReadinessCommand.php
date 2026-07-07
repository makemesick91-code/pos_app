<?php

namespace App\Console\Commands;

use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 21 — public-website:readiness.
 *
 * Evaluates public website readiness (required pages, landing content, lead
 * governance, SEO metadata, privacy/cookie, risk review, content signoff) into a
 * secret-safe PASS/WARN/FAIL report and a GO/WATCH/NO-GO decision. Never prints
 * secrets, never deploys, never bills, never opens public signup, never runs
 * Android Gradle. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class PublicWebsiteReadinessCommand extends Command
{
    protected $signature = 'public-website:readiness
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Evaluate public website / landing page readiness into a GO/WATCH/NO-GO decision.';

    public function handle(PublicWebsiteReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Public Website Readiness');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === PublicWebsiteReadinessService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === PublicWebsiteReadinessService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
