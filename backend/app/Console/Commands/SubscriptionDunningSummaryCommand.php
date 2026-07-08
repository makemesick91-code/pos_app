<?php

namespace App\Console\Commands;

use App\Services\SubscriptionRenewal\SubscriptionDunningNoticeService;
use Illuminate\Console\Command;

/**
 * Sprint 24 — subscription-renewal:dunning-summary.
 *
 * Summarizes MANUAL dunning notices by type/status/channel and asserts the manual-
 * only / no-real-sending guardrails. Never prints secrets, never sends a real
 * message. Exit code: 0 always (unless --strict on a non-GO decision).
 */
class SubscriptionDunningSummaryCommand extends Command
{
    protected $signature = 'subscription-renewal:dunning-summary
        {--json : Output JSON}
        {--strict : Fail on non-GO}';

    protected $description = 'Summarize manual subscription dunning notices by type/status/channel.';

    public function handle(SubscriptionDunningNoticeService $service): int
    {
        $summary = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Subscription Dunning Summary');
            $this->line('notices_by_type: '.json_encode($summary['notices_by_type']));
            $this->line('notices_by_status: '.json_encode($summary['notices_by_status']));
            $this->line('notices_by_channel: '.json_encode($summary['notices_by_channel']));
            $this->line('manual_only: '.($summary['manual_only'] ? 'PASS' : 'FAIL'));
            $this->line('no_real_sending: '.($summary['no_real_sending'] ? 'PASS' : 'FAIL'));
            $this->line('Decision: '.$summary['decision']);
        }

        if ($this->option('strict') && $summary['decision'] !== 'GO') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
