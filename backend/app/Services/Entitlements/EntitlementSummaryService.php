<?php

namespace App\Services\Entitlements;

use App\Models\Tenant;
use App\Services\TenantPlan\FeatureEntitlementService;
use App\Services\TenantPlan\TenantPlanResolver;

/**
 * Sprint 32 — safe, redacted read-only summaries for the admin surface and CLI
 * (ENT-R020). Combines the Sprint 26 plan/feature/usage view with the Sprint 32
 * billing access state so an operator can see exactly why a tenant is (or is not)
 * allowed to create resources — without any secret or PII leaking.
 */
class EntitlementSummaryService
{
    public function __construct(
        private readonly TenantPlanResolver $planResolver,
        private readonly FeatureEntitlementService $features,
        private readonly EntitlementUsageService $usage,
        private readonly EntitlementBillingStateService $billing,
    ) {}

    /**
     * The configured plan catalogue (limits + premium/export/report keys). No
     * tenant data — pure configuration (ENT-R020).
     *
     * @return array<string, mixed>
     */
    public function planSummary(): array
    {
        return [
            'runtime_enforcement_enabled' => (bool) config('entitlement_governance.runtime_enforcement_enabled', true),
            'fail_closed_on_unknown_plan' => (bool) config('entitlement_governance.fail_closed_on_unknown_plan', true),
            'plan_keys' => (array) config('tenant_plan.plan_keys', []),
            'default_plan' => (string) config('tenant_plan.default_plan', ''),
            'limits' => (array) config('entitlement_governance.limits', []),
            'feature_keys' => (array) config('entitlement_governance.feature_keys', []),
            'export_keys' => array_keys((array) config('entitlement_governance.exports', [])),
            'report_keys' => array_keys((array) config('entitlement_governance.reports', [])),
        ];
    }

    /**
     * A per-tenant entitlement + usage + billing summary (safe/redacted).
     *
     * @return array<string, mixed>
     */
    public function tenantSummary(Tenant $tenant): array
    {
        $plan = $this->planResolver->resolve($tenant);
        $write = $this->billing->resolveWriteAccess($tenant);

        $features = [];
        foreach ((array) config('entitlement_governance.feature_keys', []) as $key) {
            $features[$key] = $this->features->isEntitled($tenant, $key);
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_code' => $tenant->code,
            'plan_code' => $plan->planKey,
            'plan_name' => $plan->planName,
            'billing_state' => $write->billingState,
            'subscription_state' => $write->subscriptionState,
            'write_allowed' => $write->allowed,
            'write_reason_code' => $write->reasonCode,
            'degraded' => $write->degraded,
            'read_only' => $write->readOnly,
            'premium_features' => $features,
            'usage' => $this->usage->summary($tenant),
        ];
    }
}
