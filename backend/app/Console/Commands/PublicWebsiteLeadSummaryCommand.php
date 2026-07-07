<?php

namespace App\Console\Commands;

use App\Services\PublicWebsite\LeadInterestGovernanceService;
use Illuminate\Console\Command;

/**
 * Sprint 21 — public-website:lead-summary.
 *
 * Summarizes interest-only lead submissions by status/source/package/business
 * type and asserts interest-only behavior. Never prints secrets, never sends real
 * email/WhatsApp, never provisions a tenant/user/subscription/device. Exit code:
 * 0 — GO/WATCH (unless --strict on WATCH), 1 — NO_GO.
 */
class PublicWebsiteLeadSummaryCommand extends Command
{
    protected $signature = 'public-website:lead-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize interest-only public website leads.';

    public function handle(LeadInterestGovernanceService $service): int
    {
        $report = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Public Website Lead Summary');
            $this->line('New leads: '.$report['counts']['new']);
            $this->line('Spam leads: '.$report['counts']['spam']);
            $this->line('Interest-only: '.($report['interest_only'] ? 'PASS' : 'FAIL'));
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === LeadInterestGovernanceService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === LeadInterestGovernanceService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
