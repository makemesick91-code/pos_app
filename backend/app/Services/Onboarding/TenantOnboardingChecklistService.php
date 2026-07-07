<?php

namespace App\Services\Onboarding;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;

/**
 * Sprint 12 — builds the tenant onboarding checklist entirely from backend state.
 *
 * The checklist is never trusted from the client: every flag is derived from a
 * database existence query for the tenant. This is the single source used both
 * by the onboarding response and the onboarding-status endpoint.
 */
class TenantOnboardingChecklistService
{
    /**
     * @return array<string, bool>
     */
    public function buildForTenant(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->id;

        $openingSeeded = InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->where('movement_type', InventoryMovement::TYPE_OPENING)
            ->exists();

        $salesSeeded = Sale::query()->where('tenant_id', $tenantId)->exists();

        return [
            'tenant_created' => true,
            'default_store_created' => $tenant->stores()->exists(),
            'owner_user_created' => $tenant->users()
                ->where('role', User::ROLE_TENANT_OWNER)
                ->exists(),
            'subscription_assigned' => $tenant->tenantSubscriptions()->exists(),
            'demo_categories_seeded' => ProductCategory::query()->where('tenant_id', $tenantId)->exists(),
            'demo_products_seeded' => Product::query()
                ->where('tenant_id', $tenantId)
                ->where('sku', 'like', DemoCatalogFactory::SKU_PREFIX.'%')
                ->exists(),
            'demo_prices_seeded' => ProductStorePrice::query()->where('tenant_id', $tenantId)->exists(),
            'opening_inventory_seeded' => $openingSeeded,
            'demo_sales_seeded' => $salesSeeded,
            'reports_ready' => $openingSeeded || $salesSeeded,
        ];
    }
}
