<?php

namespace App\Services\TenantOnboarding;

use App\Models\TenantPlan;
use App\Services\TenantPlan\TenantPlanRegistrar;

/**
 * Sprint 33 — lists the plans that are eligible for onboarding/trial, safely
 * (ONB-R002/R024). Reads the canonical plan catalogue; never leaks pricing
 * internals beyond what plan config already declares. Output is deterministic.
 */
class OnboardingPlanReadinessService
{
    public function __construct(
        private readonly TenantPlanRegistrar $registrar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $this->registrar->ensure();

        $known = (array) config('tenant_plan.plan_keys', []);
        $allowedTrial = (array) config('onboarding_governance.trial.allowed_plans', []);

        $plans = [];

        foreach ($known as $key) {
            $plan = TenantPlan::query()->where('key', $key)->first();

            $plans[] = [
                'plan_code' => $key,
                'catalogued' => $plan instanceof TenantPlan,
                'trial_eligible' => in_array($key, $allowedTrial, true),
            ];
        }

        return [
            'trial_enabled' => (bool) config('onboarding_governance.trial.enabled', true),
            'trial_duration_days' => (int) config('onboarding_governance.trial.default_duration_days', 14),
            'onboarding_enabled' => (bool) config('onboarding_governance.enabled', true),
            'public_self_signup_mutation_enabled' => (bool) config('onboarding_governance.public_self_signup_mutation_enabled', false),
            'plans' => $plans,
        ];
    }
}
