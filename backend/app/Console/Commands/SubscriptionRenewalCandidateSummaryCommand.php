<?php

namespace App\Console\Commands;

use App\Services\SubscriptionRenewal\SubscriptionRenewalCandidateService;
use Illuminate\Console\Command;

/**
 * Sprint 24 — subscription-renewal:candidate-summary.
 *
 * Summarizes renewal candidates by status/stage/priority. Never prints secrets,
 * never creates tenants/subscriptions, never charges, never sends. Exit code: 0
 * always (unless --strict on a non-GO decision).
 */
class SubscriptionRenewalCandidateSummaryCommand extends Command
{
    protected $signature = 'subscription-renewal:candidate-summary
        {--json : Output JSON}
        {--strict : Fail on non-GO}';

    protected $description = 'Summarize subscription renewal candidates by status/stage/priority.';

    public function handle(SubscriptionRenewalCandidateService $service): int
    {
        $summary = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Subscription Renewal Candidate Summary');
            $this->line('total_candidates: '.$summary['total_candidates']);
            $this->line('by_status: '.json_encode($summary['by_status']));
            $this->line('by_stage: '.json_encode($summary['by_stage']));
            $this->line('by_priority: '.json_encode($summary['by_priority']));
            $this->line('renewal_window_count: '.$summary['renewal_window_count']);
            $this->line('grace_review_count: '.$summary['grace_review_count']);
            $this->line('overdue_review_count: '.$summary['overdue_review_count']);
            $this->line('ready_for_manual_renewal_count: '.$summary['ready_for_manual_renewal_count']);
            $this->line('Decision: '.$summary['decision']);
        }

        if ($this->option('strict') && $summary['decision'] !== 'GO') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
