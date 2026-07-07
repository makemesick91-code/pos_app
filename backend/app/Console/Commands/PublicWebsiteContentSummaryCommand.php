<?php

namespace App\Console\Commands;

use App\Services\PublicWebsite\LandingPageContentService;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 21 — public-website:content-summary.
 *
 * Summarizes public website content readiness: required vs approved pages and
 * published landing page versions, into a GO/WATCH/NO-GO decision. Never prints
 * secrets. Exit code: 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class PublicWebsiteContentSummaryCommand extends Command
{
    protected $signature = 'public-website:content-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize public website page & landing content readiness.';

    public function handle(PublicWebsiteReadinessService $readiness, LandingPageContentService $landing): int
    {
        $pages = $readiness->pagesSummary();
        $landingSummary = $landing->summary();

        $decision = $this->worst([(string) $pages['decision'], (string) $landingSummary['decision']]);

        $report = [
            'decision' => $decision,
            'pages' => $pages,
            'landing' => $landingSummary,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Public Website Content Summary');
            $this->line('Required pages: '.$pages['counts']['required']);
            $this->line('Approved pages: '.$pages['counts']['approved_or_published']);
            $this->line('Published landing versions: '.$landingSummary['counts']['published']);
            $this->line('Decision: '.$decision);
        }

        return $this->exitCode($decision);
    }

    /**
     * @param array<int,string> $decisions
     */
    private function worst(array $decisions): string
    {
        if (in_array(PublicWebsiteReadinessService::DECISION_NO_GO, $decisions, true)) {
            return PublicWebsiteReadinessService::DECISION_NO_GO;
        }
        if (in_array(PublicWebsiteReadinessService::DECISION_WATCH, $decisions, true)) {
            return PublicWebsiteReadinessService::DECISION_WATCH;
        }

        return PublicWebsiteReadinessService::DECISION_GO;
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
