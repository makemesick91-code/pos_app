<?php

namespace App\Services\Entitlements;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPlan\TenantUsageMeter;

/**
 * Sprint 32 — computes a tenant's CURRENT usage for the runtime-enforced
 * resources (branches/outlets/registers, users, cashiers, devices, exports).
 *
 * Counts are always tenant-isolated and derived from real DB state — never
 * trusted from the client. Where Sprint 26 already meters a key
 * (branches/users/devices/exports) that authoritative meter is reused so there
 * is a single source of truth; the cashier count is derived from the cashier
 * role. Deleted/revoked resources are not counted (only ACTIVE devices count,
 * matching the Sprint 26 device meter).
 */
class EntitlementUsageService
{
    public function __construct(
        private readonly TenantUsageMeter $meter,
    ) {}

    /**
     * Current usage for a Sprint 32 limit alias (branch/outlet/register/user/
     * cashier/device). Returns the resolved count and the underlying Sprint 26
     * limit key so the caller can evaluate the plan cap.
     *
     * @return array{limit_key: string, current: int}
     */
    public function usageFor(Tenant $tenant, string $limitAlias): array
    {
        $config = (array) config('entitlement_governance.limits.'.$limitAlias, []);
        $limitKey = (string) ($config['limit_key'] ?? '');

        $current = match ($limitAlias) {
            'cashier' => $this->cashierCount($tenant),
            default => (int) ($this->meter->currentUsage($tenant, $limitKey) ?? 0),
        };

        return ['limit_key' => $limitKey, 'current' => $current];
    }

    public function branchCount(Tenant $tenant): int
    {
        return Store::query()->where('tenant_id', $tenant->id)->count();
    }

    public function userCount(Tenant $tenant): int
    {
        return User::query()->where('tenant_id', $tenant->id)->count();
    }

    public function cashierCount(Tenant $tenant): int
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_CASHIER)
            ->count();
    }

    public function activeDeviceCount(Tenant $tenant): int
    {
        return RegisteredDevice::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', RegisteredDevice::STATUS_ACTIVE)
            ->count();
    }

    /**
     * A safe, redacted usage summary (no PII) for admin/CLI output.
     *
     * @return array<string, array{current: int, limit: ?int, unlimited: bool}>
     */
    public function summary(Tenant $tenant): array
    {
        $resolver = app(\App\Services\TenantPlan\TenantPlanResolver::class);
        $plan = $resolver->resolve($tenant);

        $out = [];
        foreach ((array) config('entitlement_governance.limits', []) as $alias => $meta) {
            $limitKey = (string) ($meta['limit_key'] ?? '');
            $limit = $plan->limit($limitKey);
            $usage = $this->usageFor($tenant, $alias);

            $out[$alias] = [
                'current' => $usage['current'],
                'limit' => $limit === null ? null : ($limit['limit'] ?? null),
                'unlimited' => $limit !== null && (bool) ($limit['unlimited'] ?? false),
            ];
        }

        return $out;
    }
}
