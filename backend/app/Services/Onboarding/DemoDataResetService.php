<?php

namespace App\Services\Onboarding;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Tenant;
use App\Models\TenantOnboardingRun;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 12 — guarded reset of demo data for a tenant.
 *
 * Safety is the whole point: this NEVER blindly deletes tenant data. It deletes
 * only rows recorded in a backend-owned demo manifest (the ids seeded by an
 * onboarding/demo-seed run for this tenant) AND still owned by the tenant. Data
 * that was never seeded by onboarding is not in any manifest, so it can never be
 * deleted here. Supports a dry-run summary and audit-logs every reset. There is
 * no destructive tenant wipe. See Sprint 12 evidence.
 */
class DemoDataResetService
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @return array{dry_run: bool, deleted: array<string, int>, manifest_runs: int}
     */
    public function reset(User $actor, Tenant $tenant, bool $dryRun = false, ?Request $request = null): array
    {
        $runs = $tenant->onboardingRuns()->get();
        $manifest = $this->mergedManifest($runs);

        $summary = $dryRun
            ? $this->countOnly($tenant, $manifest)
            : $this->deleteDemoData($tenant, $manifest, $runs);

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_DEMO_DATA_RESET,
            targetType: AdminAuditLog::TARGET_TENANT,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            metadata: [
                'dry_run' => $dryRun,
                'deleted_products' => $summary['products'] ?? 0,
                'deleted_categories' => $summary['categories'] ?? 0,
                'deleted_prices' => $summary['prices'] ?? 0,
                'deleted_movements' => $summary['movements'] ?? 0,
                'manifest_runs' => $runs->count(),
            ],
            request: $request,
        );

        return [
            'dry_run' => $dryRun,
            'deleted' => $summary,
            'manifest_runs' => $runs->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TenantOnboardingRun>  $runs
     * @return array<string, array<int, int>>
     */
    private function mergedManifest($runs): array
    {
        $merged = [
            'category_ids' => [],
            'product_ids' => [],
            'price_ids' => [],
            'movement_ids' => [],
        ];

        foreach ($runs as $run) {
            $manifest = $run->demoManifest();
            foreach ($merged as $key => $ids) {
                $merged[$key] = array_merge($ids, $manifest[$key] ?? []);
            }
        }

        foreach ($merged as $key => $ids) {
            $merged[$key] = array_values(array_unique(array_map('intval', $ids)));
        }

        return $merged;
    }

    /**
     * @param  array<string, array<int, int>>  $manifest
     * @return array<string, int>
     */
    private function countOnly(Tenant $tenant, array $manifest): array
    {
        return [
            'movements' => $this->ownedQuery(InventoryMovement::class, $tenant, $manifest['movement_ids'])->count(),
            'prices' => $this->ownedQuery(ProductStorePrice::class, $tenant, $manifest['price_ids'])->count(),
            'products' => $this->ownedQuery(Product::class, $tenant, $manifest['product_ids'])->count(),
            'categories' => $this->ownedQuery(ProductCategory::class, $tenant, $manifest['category_ids'])->count(),
        ];
    }

    /**
     * @param  array<string, array<int, int>>  $manifest
     * @param  \Illuminate\Support\Collection<int, TenantOnboardingRun>  $runs
     * @return array<string, int>
     */
    private function deleteDemoData(Tenant $tenant, array $manifest, $runs): array
    {
        return DB::transaction(function () use ($tenant, $manifest, $runs): array {
            // FK-safe order: ledger + price overrides, then products, then categories.
            $movements = $this->ownedQuery(InventoryMovement::class, $tenant, $manifest['movement_ids'])->delete();
            $prices = $this->ownedQuery(ProductStorePrice::class, $tenant, $manifest['price_ids'])->delete();
            $products = $this->ownedQuery(Product::class, $tenant, $manifest['product_ids'])->delete();
            $categories = $this->ownedQuery(ProductCategory::class, $tenant, $manifest['category_ids'])->delete();

            foreach ($runs as $run) {
                $run->demo_data_reset_at = Carbon::now();
                $run->save();
            }

            return [
                'movements' => (int) $movements,
                'prices' => (int) $prices,
                'products' => (int) $products,
                'categories' => (int) $categories,
            ];
        });
    }

    /**
     * A delete/count query scoped to BOTH the tenant and the manifest ids. The
     * tenant scope is the safety net: even a corrupt manifest can only ever hit
     * this tenant's own rows.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<int, int>  $ids
     */
    private function ownedQuery(string $model, Tenant $tenant, array $ids)
    {
        return $model::query()
            ->where('tenant_id', $tenant->id)
            ->when($ids === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->whereIn('id', $ids === [] ? [0] : $ids);
    }
}
