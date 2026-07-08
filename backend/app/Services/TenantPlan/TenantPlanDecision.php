<?php

namespace App\Services\TenantPlan;

/**
 * Sprint 26 — the immutable result of resolving a tenant's plan.
 *
 * Produced only by TenantPlanResolver. Carries the authoritative plan key/name,
 * whether the tenant has an explicit active assignment (vs the safe default
 * plan), the per-plan entitlement flags, and the per-plan usage limits. Never
 * carries secrets.
 */
final class TenantPlanDecision
{
    /**
     * @param  array<string, bool>  $entitlements
     * @param  array<string, array{unlimited: bool, limit: ?int, period: string}>  $limits
     */
    public function __construct(
        public readonly string $planKey,
        public readonly string $planName,
        public readonly bool $hasExplicitAssignment,
        public readonly ?int $assignmentId,
        public readonly array $entitlements,
        public readonly array $limits,
    ) {}

    public function grantsByPlan(string $entitlementKey): bool
    {
        return (bool) ($this->entitlements[$entitlementKey] ?? false);
    }

    /**
     * @return array{unlimited: bool, limit: ?int, period: string}|null
     */
    public function limit(string $limitKey): ?array
    {
        return $this->limits[$limitKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan_key' => $this->planKey,
            'plan_name' => $this->planName,
            'has_explicit_assignment' => $this->hasExplicitAssignment,
            'assignment_id' => $this->assignmentId,
            'entitlements' => $this->entitlements,
            'limits' => $this->limits,
        ];
    }
}
