<?php

namespace App\Services\TenantPlan;

use App\Models\Tenant;

/**
 * Sprint 26 — the single server-side service that evaluates a tenant's usage
 * against its plan limit before a protected mutation (TPE-R003).
 *
 * canUse() returns a full UsageLimitDecision so the middleware, admin summary,
 * and enforcement audit all share one authoritative answer. Unlimited plans and
 * limits that are not configured for a plan allow through; a meterable limit that
 * is at/over cap denies with the stable USAGE_LIMIT_EXCEEDED code (TPE-R009).
 * Tenant lifecycle enforcement always runs first (TPE-R004).
 */
class TenantUsageLimitService
{
    public function __construct(
        private readonly TenantPlanResolver $resolver,
        private readonly TenantUsageMeter $meter,
    ) {}

    public function canUse(Tenant $tenant, string $limitKey, int $increment = 1): UsageLimitDecision
    {
        $plan = $this->resolver->resolve($tenant);
        $limit = $plan->limit($limitKey);
        $period = (string) ($limit['period'] ?? config('tenant_plan.usage_limits.'.$limitKey.'.period', 'lifetime'));

        // Unlimited, or the plan does not configure this limit → allowed.
        if ($limit === null || ($limit['unlimited'] ?? false) === true || $limit['limit'] === null) {
            $current = $this->meter->currentUsage($tenant, $limitKey);

            return new UsageLimitDecision(
                allowed: true,
                limitKey: $limitKey,
                unlimited: true,
                limit: null,
                current: $current,
                remaining: null,
                meterable: $this->meter->isMeterable($limitKey),
                code: null,
                period: $period,
            );
        }

        $cap = (int) $limit['limit'];
        $current = $this->meter->currentUsage($tenant, $limitKey);

        // Declared but not meterable yet — allow, but report it explicitly.
        if ($current === null) {
            return new UsageLimitDecision(
                allowed: true,
                limitKey: $limitKey,
                unlimited: false,
                limit: $cap,
                current: null,
                remaining: null,
                meterable: false,
                code: null,
                period: $period,
            );
        }

        $allowed = ($current + $increment) <= $cap;
        $remaining = max(0, $cap - $current);

        return new UsageLimitDecision(
            allowed: $allowed,
            limitKey: $limitKey,
            unlimited: false,
            limit: $cap,
            current: $current,
            remaining: $remaining,
            meterable: true,
            code: $allowed ? null : 'USAGE_LIMIT_EXCEEDED',
            period: $period,
        );
    }

    public function currentUsage(Tenant $tenant, string $limitKey): ?int
    {
        return $this->meter->currentUsage($tenant, $limitKey);
    }

    public function remaining(Tenant $tenant, string $limitKey): ?int
    {
        return $this->canUse($tenant, $limitKey, 0)->remaining;
    }
}
