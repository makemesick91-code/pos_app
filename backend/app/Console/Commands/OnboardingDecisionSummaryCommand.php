<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:decision-summary. Summarizes failed/blocked provisioning
 * steps grouped by stable reason code. No PII/secrets (ONB-R023/R024).
 */
class OnboardingDecisionSummaryCommand extends Command
{
    protected $signature = 'onboarding:decision-summary {--json : Output JSON}';

    protected $description = 'Summarize failed/blocked onboarding provisioning steps.';

    public function handle(OnboardingSummaryService $service): int
    {
        $summary = $service->decisionSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Onboarding Decision Summary');
        $this->line('Failed steps total: '.$summary['failed_steps_total']);
        $this->line('Failed runs total: '.$summary['failed_runs_total']);

        $this->line('By reason code:');
        foreach ($summary['by_reason_code'] as $reason => $count) {
            $this->line('  '.$reason.': '.$count);
        }

        $this->line('By step key:');
        foreach ($summary['by_step_key'] as $step => $count) {
            $this->line('  '.$step.': '.$count);
        }

        return self::SUCCESS;
    }
}
