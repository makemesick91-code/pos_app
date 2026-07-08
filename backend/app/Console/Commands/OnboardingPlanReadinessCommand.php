<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingPlanReadinessService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:plan-readiness. Lists the plans eligible for onboarding/
 * trial. Read-only; never prints secrets/PII (ONB-R024).
 */
class OnboardingPlanReadinessCommand extends Command
{
    protected $signature = 'onboarding:plan-readiness {--json : Output JSON}';

    protected $description = 'List plans eligible for onboarding/trial activation.';

    public function handle(OnboardingPlanReadinessService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Onboarding Plan Readiness');
        $this->line('Onboarding enabled: '.($report['onboarding_enabled'] ? 'yes' : 'no'));
        $this->line('Trial enabled: '.($report['trial_enabled'] ? 'yes' : 'no').' ('.$report['trial_duration_days'].'d)');
        $this->line('Public self-signup mutation: '.($report['public_self_signup_mutation_enabled'] ? 'ENABLED' : 'disabled'));

        foreach ($report['plans'] as $plan) {
            $this->line(sprintf(
                '- %s: catalogued=%s trial_eligible=%s',
                $plan['plan_code'],
                $plan['catalogued'] ? 'yes' : 'no',
                $plan['trial_eligible'] ? 'yes' : 'no',
            ));
        }

        return self::SUCCESS;
    }
}
