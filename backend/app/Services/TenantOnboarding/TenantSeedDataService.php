<?php

namespace App\Services\TenantOnboarding;

use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;

/**
 * Sprint 33 — seeds SAFE, DETERMINISTIC, TENANT-ISOLATED default data for a new
 * tenant (ONB-R012). This is a small fixed set of default product categories
 * scoped to the tenant + first store. It is idempotent (firstOrCreate) and seeds
 * NO fake production transactions/sales by default (config seed_demo_transactions
 * is false).
 */
class TenantSeedDataService
{
    /** Deterministic default categories for a fresh UMKM tenant. */
    private const DEFAULT_CATEGORIES = ['Umum', 'Makanan', 'Minuman'];

    /**
     * @return array{categories_seeded: int, demo_transactions: bool}
     */
    public function seed(Tenant $tenant, Store $branch): array
    {
        if (! (bool) config('onboarding_governance.provisioning.seed_default_data', true)) {
            return ['categories_seeded' => 0, 'demo_transactions' => false];
        }

        $seeded = 0;

        foreach (self::DEFAULT_CATEGORIES as $index => $name) {
            $category = ProductCategory::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'store_id' => $branch->id,
                    'name' => $name,
                ],
                [
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );

            if ($category->wasRecentlyCreated) {
                $seeded++;
            }
        }

        return [
            'categories_seeded' => $seeded,
            'demo_transactions' => (bool) config('onboarding_governance.provisioning.seed_demo_transactions', false),
        ];
    }
}
