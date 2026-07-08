<?php

namespace App\Services\TenantPlan;

use App\Models\PlanEntitlement;
use App\Models\PlanUsageLimit;
use App\Models\TenantEntitlementOverride;
use App\Models\TenantPlan;
use App\Models\TenantPlanAssignment;

/**
 * Sprint 26 — read-only, secret-safe governance summaries for tenant plan,
 * feature entitlement, and usage-limit governance. Used by the admin summary API
 * and the tenant-plan:* summary commands. Never exposes reasons verbatim or any
 * secret.
 */
class TenantPlanSummaryService
{
    public function __construct(
        private readonly TenantPlanRegistrar $registrar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function governanceSummary(): array
    {
        $this->registrar->ensure();

        return [
            'plans' => TenantPlan::query()->count(),
            'active_plans' => TenantPlan::query()->where('status', TenantPlan::STATUS_ACTIVE)->count(),
            'entitlement_registry' => count((array) config('tenant_plan.entitlements', [])),
            'usage_limit_registry' => count((array) config('tenant_plan.usage_limits', [])),
            'default_plan' => (string) config('tenant_plan.default_plan'),
            'assignments' => $this->entitlementSummary()['assignments'],
            'active_overrides' => TenantEntitlementOverride::query()
                ->where('status', TenantEntitlementOverride::STATUS_ACTIVE)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entitlementSummary(): array
    {
        $this->registrar->ensure();

        $assignmentsByPlan = TenantPlanAssignment::query()
            ->where('tenant_plan_assignments.status', TenantPlanAssignment::STATUS_ACTIVE)
            ->join('tenant_plans', 'tenant_plans.id', '=', 'tenant_plan_assignments.tenant_plan_id')
            ->selectRaw('tenant_plans.key as plan_key, COUNT(*) as total')
            ->groupBy('tenant_plans.key')
            ->pluck('total', 'plan_key')
            ->toArray();

        $enabledPerPlan = [];
        foreach (TenantPlan::query()->with('entitlements')->get() as $plan) {
            $enabledPerPlan[$plan->key] = $plan->entitlements->where('enabled', true)->count();
        }

        $overridesByKey = TenantEntitlementOverride::query()
            ->where('status', TenantEntitlementOverride::STATUS_ACTIVE)
            ->selectRaw('entitlement_key, COUNT(*) as total')
            ->groupBy('entitlement_key')
            ->pluck('total', 'entitlement_key')
            ->toArray();

        return [
            'entitlement_keys' => array_keys((array) config('tenant_plan.entitlements', [])),
            'assignments' => [
                'active_by_plan' => $assignmentsByPlan,
                'total_active' => array_sum($assignmentsByPlan),
            ],
            'enabled_entitlements_per_plan' => $enabledPerPlan,
            'active_overrides_by_key' => $overridesByKey,
            'total_active_overrides' => array_sum($overridesByKey),
            'plan_entitlement_rows' => PlanEntitlement::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usageLimitSummary(): array
    {
        $this->registrar->ensure();

        $registry = (array) config('tenant_plan.usage_limits', []);

        $limits = [];
        foreach ($registry as $key => $meta) {
            $rows = PlanUsageLimit::query()->where('limit_key', $key)->get();
            $limits[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'period' => (string) ($meta['period'] ?? 'lifetime'),
                'meterable' => (bool) ($meta['meterable'] ?? false),
                'plans_defining' => $rows->count(),
                'unlimited_plans' => $rows->where('unlimited', true)->count(),
            ];
        }

        return [
            'usage_limit_keys' => array_keys($registry),
            'meterable_limits' => array_keys(array_filter($registry, fn ($m) => (bool) ($m['meterable'] ?? false))),
            'deferred_limits' => array_keys(array_filter($registry, fn ($m) => ! (bool) ($m['meterable'] ?? false))),
            'limits' => $limits,
            'plan_usage_limit_rows' => PlanUsageLimit::query()->count(),
        ];
    }
}
