<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;

/**
 * Sprint 26 — the single server-side decision point for whether a tenant is
 * entitled to a feature (TPE-R002).
 *
 * The plan grant is the base; an ACTIVE tenant entitlement override (platform
 * admin only) refines it up or down. The Android/POS client is UX only and never
 * the authority. This service does NOT decide tenant lifecycle access — the
 * tenant.lifecycle guard runs first (TPE-R004), so a suspended/cancelled/archived
 * tenant is already blocked before entitlement is ever consulted (TPE-R005).
 */
class FeatureEntitlementService
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
    ) {}

    public function decide(Tenant $tenant, string $feature): EntitlementDecision
    {
        $plan = $this->resolver->resolve($tenant);
        $entitled = $plan->grantsByPlan($feature);
        $source = EntitlementDecision::SOURCE_PLAN;

        $override = $this->activeOverrideFor($tenant, $feature);
        if ($override instanceof TenantEntitlementOverride) {
            $entitled = (bool) $override->enabled;
            $source = EntitlementDecision::SOURCE_OVERRIDE;
        }

        return new EntitlementDecision(
            entitled: $entitled,
            feature: $feature,
            planKey: $plan->planKey,
            source: $source,
            code: $entitled ? null : 'FEATURE_NOT_ENTITLED',
        );
    }

    public function isEntitled(Tenant $tenant, string $feature): bool
    {
        return $this->decide($tenant, $feature)->entitled;
    }

    /**
     * The effective entitlement map for a tenant (plan flags with active
     * overrides applied). Read-only governance view.
     *
     * @return array<string, array{entitled: bool, source: string}>
     */
    public function effectiveMap(Tenant $tenant): array
    {
        $plan = $this->resolver->resolve($tenant);
        $keys = array_keys((array) config('tenant_plan.entitlements', []));

        $out = [];
        foreach ($keys as $feature) {
            $decision = $this->decide($tenant, $feature);
            $out[$feature] = ['entitled' => $decision->entitled, 'source' => $decision->source];
        }

        // Ensure any plan-defined key not in the registry is still represented.
        foreach (array_keys($plan->entitlements) as $feature) {
            if (! array_key_exists($feature, $out)) {
                $decision = $this->decide($tenant, $feature);
                $out[$feature] = ['entitled' => $decision->entitled, 'source' => $decision->source];
            }
        }

        return $out;
    }

    private function activeOverrideFor(Tenant $tenant, string $feature): ?TenantEntitlementOverride
    {
        return $tenant->activeEntitlementOverrides()
            ->firstWhere('entitlement_key', $feature);
    }
}
