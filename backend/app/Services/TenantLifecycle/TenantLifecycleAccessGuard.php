<?php

namespace App\Services\TenantLifecycle;

use App\Models\Tenant;

/**
 * Sprint 25 — the single decision point for whether a tenant may perform an
 * operational (POS) action (TLS-R003, TLS-R007).
 *
 * Both the runtime middleware and the enforcement-audit command reuse this
 * guard so the allowed/blocked answer is never recomputed ad-hoc in a
 * controller. Delegates the lifecycle status computation to
 * TenantLifecycleService.
 */
class TenantLifecycleAccessGuard
{
    public function __construct(
        private readonly TenantLifecycleService $lifecycle,
    ) {}

    public function decide(Tenant $tenant): TenantLifecycleDecision
    {
        return $this->lifecycle->resolve($tenant);
    }

    public function allows(Tenant $tenant): bool
    {
        return $this->decide($tenant)->allowed;
    }

    public function blocks(Tenant $tenant): bool
    {
        return ! $this->allows($tenant);
    }
}
