<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;

/**
 * Sprint 26 — the single guard reused by the EnsureTenantEntitled middleware and
 * the enforcement-audit command so the entitled/denied answer for a feature is
 * never recomputed ad-hoc in a controller (TPE-R002). Delegates to
 * FeatureEntitlementService.
 */
class TenantEntitlementGuard
{
    public function __construct(
        private readonly FeatureEntitlementService $entitlements,
    ) {}

    public function decide(Tenant $tenant, string $feature): EntitlementDecision
    {
        return $this->entitlements->decide($tenant, $feature);
    }

    public function allows(Tenant $tenant, string $feature): bool
    {
        return $this->decide($tenant, $feature)->entitled;
    }

    public function blocks(Tenant $tenant, string $feature): bool
    {
        return ! $this->allows($tenant, $feature);
    }
}
