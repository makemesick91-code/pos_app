<?php

namespace App\Services\TenantPlan;

use App\Models\Product;
use App\Models\RegisteredDevice;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;

/**
 * Sprint 26 — computes a tenant's CURRENT usage for a limit key from real DB
 * counts (TPE-R003). Where the current count is already authoritative in the DB
 * (products, stores, users, active devices, monthly sales) it is derived on read
 * — no fragile counters are stored. A limit that is declared but not yet
 * meterable returns null so callers can report "not meterable yet" explicitly
 * rather than a silent zero.
 */
class TenantUsageMeter
{
    /**
     * @return int|null Current usage, or null when the limit is not meterable.
     */
    public function currentUsage(Tenant $tenant, string $limitKey): ?int
    {
        return match ($limitKey) {
            'branches.max' => Store::query()->where('tenant_id', $tenant->id)->count(),
            'users.max' => User::query()->where('tenant_id', $tenant->id)->count(),
            'devices.max' => RegisteredDevice::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', RegisteredDevice::STATUS_ACTIVE)
                ->count(),
            'products.max' => Product::query()->where('tenant_id', $tenant->id)->count(),
            'transactions.monthly' => Sale::query()
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            default => null,
        };
    }

    public function isMeterable(string $limitKey): bool
    {
        $meta = (array) config('tenant_plan.usage_limits.'.$limitKey, []);

        return (bool) ($meta['meterable'] ?? false);
    }
}
