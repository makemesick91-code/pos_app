<?php

namespace App\Services\TenantPlan;

use App\Models\Product;
use App\Models\RegisteredDevice;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UsageEventLedger\ReportExportMeteringService;

/**
 * Sprint 26 — computes a tenant's CURRENT usage for a limit key from real DB
 * counts (TPE-R003). Where the current count is already authoritative in the DB
 * (products, stores, users, active devices, monthly sales) it is derived on read
 * — no fragile counters are stored. A limit that is declared but not yet
 * meterable returns null so callers can report "not meterable yet" explicitly
 * rather than a silent zero.
 *
 * Sprint 27 — the previously deferred `reports.exports.monthly` meter is now live:
 * its current usage is derived from the append-only tenant usage event ledger
 * (UEL-R006), so a report export limit can actually be enforced.
 */
class TenantUsageMeter
{
    public function __construct(
        private readonly ReportExportMeteringService $reportExportMetering,
    ) {}

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
            'reports.exports.monthly' => $this->reportExportMetering->currentMonthlyUsage($tenant),
            default => null,
        };
    }

    public function isMeterable(string $limitKey): bool
    {
        // Look the key up literally: usage-limit keys contain dots
        // (e.g. reports.exports.monthly), so config() dot-notation would wrongly
        // treat them as nested paths and always return false (Sprint 27 latent bug,
        // fixed Sprint 28 with a regression test).
        $registry = (array) config('tenant_plan.usage_limits', []);
        $meta = (array) ($registry[$limitKey] ?? []);

        return (bool) ($meta['meterable'] ?? false);
    }
}
