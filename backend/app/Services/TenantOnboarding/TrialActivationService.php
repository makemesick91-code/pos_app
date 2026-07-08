<?php

namespace App\Services\TenantOnboarding;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\TenantPlanAssignment;
use App\Models\TenantSubscription;
use App\Services\TenantPlan\TenantPlanRegistrar;
use App\Services\TenantPlan\TenantPlanResolver;
use Illuminate\Support\Carbon;

/**
 * Sprint 33 — activates a governed, time-bounded trial for a freshly created
 * tenant (ONB-R007). It assigns the resolved plan (making TenantPlanResolver
 * return it — ONB-R002) and creates a TRIAL TenantSubscription with a bounded
 * `trial_ends_at`. It NEVER marks any invoice paid and never grants a paid
 * state; paid access only ever follows the trusted Sprint 30 collection layer.
 *
 * Fail-closed: an unknown plan, or a plan not eligible for trial, raises an
 * OnboardingException — there is no silent unlimited/free fallback (ONB-R003).
 */
class TrialActivationService
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
        private readonly TenantPlanRegistrar $registrar,
    ) {}

    /**
     * @return array{subscription: TenantSubscription, assignment: TenantPlanAssignment, trial: bool}
     */
    public function activate(Tenant $tenant, OnboardingRequestData $data): array
    {
        $planKey = $data->planCode;

        // Materialize the plan catalogue from config before resolving (ONB-R002).
        $this->registrar->ensure();

        $plan = TenantPlan::query()->where('key', $planKey)->first();

        if (! $plan instanceof TenantPlan) {
            // Fail closed — never fall back to unlimited (ONB-R003).
            throw new OnboardingException('UNKNOWN_PLAN', "Plan '{$planKey}' could not be resolved; failing closed.");
        }

        $trialRequested = $data->withTrial;

        if ($trialRequested && ! (bool) config('onboarding_governance.trial.enabled', true)) {
            throw new OnboardingException('TRIAL_DISABLED', 'Trial activation is disabled by governance.');
        }

        $allowedTrialPlans = (array) config('onboarding_governance.trial.allowed_plans', []);

        if ($trialRequested && ! in_array($planKey, $allowedTrialPlans, true)) {
            throw new OnboardingException(
                'PLAN_NOT_ALLOWED_FOR_TRIAL',
                "Plan '{$planKey}' is not eligible for trial activation.",
            );
        }

        $assignment = $this->assignPlan($tenant, $plan);

        // Confirm the plan now resolves through the canonical resolver (ONB-R002).
        $resolved = $this->resolver->resolve($tenant->refresh());

        if ($resolved->planKey !== $planKey) {
            throw new OnboardingException('UNKNOWN_PLAN', 'Plan assignment did not resolve to the requested plan; failing closed.');
        }

        $subscription = $this->activateSubscription($tenant, $plan, $trialRequested);

        // Keep the denormalized tenant columns consistent with the subscription.
        $tenant->subscription_plan = $planKey;
        $tenant->subscription_status = $subscription->status;
        $tenant->subscription_started_at = $subscription->starts_at;
        $tenant->subscription_ends_at = $subscription->trial_ends_at ?? $subscription->ends_at;
        $tenant->save();

        return ['subscription' => $subscription, 'assignment' => $assignment, 'trial' => $trialRequested];
    }

    private function assignPlan(Tenant $tenant, TenantPlan $plan): TenantPlanAssignment
    {
        // Supersede any current active assignment, then append the new one
        // (mirrors the Sprint 26 TenantPlanAssignmentService writer; assigned_by
        // is nullable so onboarding needs no live admin actor).
        TenantPlanAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantPlanAssignment::STATUS_ACTIVE)
            ->update(['status' => TenantPlanAssignment::STATUS_EXPIRED]);

        return TenantPlanAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_plan_id' => $plan->id,
            'status' => TenantPlanAssignment::STATUS_ACTIVE,
            'effective_from' => Carbon::now(),
            'effective_until' => null,
            'source' => TenantPlanAssignment::SOURCE_SYSTEM,
            'assigned_by_user_id' => null,
            'reason' => 'onboarding_trial_activation',
            'metadata' => ['origin' => 'sprint33_onboarding'],
        ]);
    }

    private function activateSubscription(Tenant $tenant, TenantPlan $plan, bool $trial): TenantSubscription
    {
        // An existing subscription for this tenant is reused (idempotent retry).
        $existing = $tenant->tenantSubscriptions()->latest('id')->first();

        if ($existing instanceof TenantSubscription) {
            return $existing;
        }

        $subscriptionPlan = $this->subscriptionPlanFor($plan);
        $now = Carbon::now();

        $attributes = [
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $subscriptionPlan->id,
            'status' => $trial ? TenantSubscription::STATUS_TRIAL : TenantSubscription::STATUS_ACTIVE,
            'starts_at' => $now,
            'ends_at' => null,
            'trial_ends_at' => null,
        ];

        if ($trial) {
            $days = (int) config('onboarding_governance.trial.default_duration_days', 14);
            $max = (int) config('onboarding_governance.trial.max_duration_days', 30);
            $days = max(1, min($days, $max));
            $attributes['trial_ends_at'] = $now->copy()->addDays($days);
        } else {
            $attributes['ends_at'] = $now->copy()->addMonth();
        }

        return TenantSubscription::query()->create($attributes);
    }

    private function subscriptionPlanFor(TenantPlan $plan): SubscriptionPlan
    {
        // The Sprint 10 SubscriptionPlan is a separate (device/store cap) model
        // from the Sprint 26 TenantPlan that governs entitlements. We keep the
        // subscription caps generous so they never fight the entitlement limits,
        // which remain the single source of truth (ONB-R013).
        return SubscriptionPlan::query()->firstOrCreate(
            ['code' => $plan->key],
            [
                'name' => $plan->name ?? ucfirst($plan->key),
                'price_monthly' => 0,
                'max_stores' => 9999,
                'max_devices' => 9999,
                'is_active' => true,
            ],
        );
    }
}
