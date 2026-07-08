<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;
use App\Models\TenantPlan;
use App\Models\TenantPlanAssignment;

/**
 * Sprint 26 — the single server-side source of truth for which plan a tenant is
 * on and what that plan grants (TPE-R001).
 *
 * Resolution order:
 *   1. The tenant's ACTIVE plan assignment (within its effective window).
 *   2. Otherwise the safe default plan (config tenant_plan.default_plan) — a
 *      real, RESTRICTED plan, never a bypass. A tenant with no assignment is
 *      therefore never "unlimited".
 *
 * Plan entitlement flags and usage limits are always read from the persisted
 * catalogue (TenantPlanRegistrar keeps it in sync with config). The decision is
 * always recomputed here and never trusted from the client (TPE-R002).
 */
class TenantPlanResolver
{
    public function __construct(
        private readonly TenantPlanRegistrar $registrar,
    ) {}

    public function resolve(Tenant $tenant): TenantPlanDecision
    {
        $this->registrar->ensure();

        $assignment = $tenant->activePlanAssignment();

        if ($assignment instanceof TenantPlanAssignment) {
            $plan = $assignment->plan()->with(['entitlements', 'usageLimits'])->first();

            if ($plan instanceof TenantPlan) {
                return $this->decisionFor($plan, true, $assignment->id);
            }
        }

        $plan = TenantPlan::query()
            ->with(['entitlements', 'usageLimits'])
            ->where('key', $this->defaultPlanKey())
            ->first();

        if (! $plan instanceof TenantPlan) {
            // Catalogue not populated for the default plan — resolve entirely
            // from the canonical config definition so resolution is never unsafe.
            return $this->decisionFromConfig($this->defaultPlanKey());
        }

        return $this->decisionFor($plan, false, null);
    }

    private function decisionFor(TenantPlan $plan, bool $explicit, ?int $assignmentId): TenantPlanDecision
    {
        $entitlements = [];
        foreach ($plan->entitlements as $row) {
            $entitlements[$row->entitlement_key] = (bool) $row->enabled;
        }

        $limits = [];
        foreach ($plan->usageLimits as $row) {
            $limits[$row->limit_key] = [
                'unlimited' => (bool) $row->unlimited,
                'limit' => $row->unlimited ? null : ($row->limit_value === null ? null : (int) $row->limit_value),
                'period' => (string) $row->period,
            ];
        }

        return new TenantPlanDecision(
            planKey: $plan->key,
            planName: $plan->name,
            hasExplicitAssignment: $explicit,
            assignmentId: $assignmentId,
            entitlements: $entitlements,
            limits: $limits,
        );
    }

    private function decisionFromConfig(string $planKey): TenantPlanDecision
    {
        $definition = (array) config('tenant_plan.plans.'.$planKey, []);
        $limitMeta = (array) config('tenant_plan.usage_limits', []);

        $entitlements = [];
        foreach ((array) ($definition['entitlements'] ?? []) as $key => $enabled) {
            $entitlements[$key] = (bool) $enabled;
        }

        $limits = [];
        foreach ((array) ($definition['limits'] ?? []) as $key => $def) {
            $unlimited = (bool) ($def['unlimited'] ?? false);
            $limits[$key] = [
                'unlimited' => $unlimited,
                'limit' => $unlimited ? null : (array_key_exists('limit', (array) $def) ? (int) $def['limit'] : null),
                'period' => (string) ($limitMeta[$key]['period'] ?? 'lifetime'),
            ];
        }

        return new TenantPlanDecision(
            planKey: $planKey,
            planName: (string) ($definition['name'] ?? ucfirst($planKey)),
            hasExplicitAssignment: false,
            assignmentId: null,
            entitlements: $entitlements,
            limits: $limits,
        );
    }

    public function defaultPlanKey(): string
    {
        return (string) config('tenant_plan.default_plan', 'starter');
    }
}
