<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:trial-summary. Summarizes active/expired trials and run
 * counts by status. Aggregates only; no PII/secrets (ONB-R024).
 */
class OnboardingTrialSummaryCommand extends Command
{
    protected $signature = 'onboarding:trial-summary {--json : Output JSON}';

    protected $description = 'Summarize active/expired onboarding trials.';

    public function handle(OnboardingSummaryService $service): int
    {
        $summary = $service->trialSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Onboarding Trial Summary');
        $this->line('Total runs: '.$summary['total_runs']);
        $this->line('Trials total: '.$summary['trials_total']);
        $this->line('Trials active: '.$summary['trials_active']);
        $this->line('Trials expired: '.$summary['trials_expired']);

        foreach ($summary['by_status'] as $status => $count) {
            $this->line('  '.$status.': '.$count);
        }

        return self::SUCCESS;
    }
}
