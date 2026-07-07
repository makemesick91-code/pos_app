<?php

namespace App\Services\Onboarding;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sprint 12 — seeds a recognizable, tenant-owned demo catalog into a tenant.
 *
 * Every row is created for the target tenant and default/target store. Opening
 * stock is written through the inventory ledger (InventoryMovementService,
 * OPENING) — never a mutable stock column. The seed is idempotent: categories/
 * products/prices are matched by a stable key so repeating it never creates
 * unlimited duplicates, and it returns a demo manifest (the ids it owns) so the
 * guarded reset can delete exactly what was seeded.
 *
 * Demo sales are intentionally NOT created in Sprint 12: creating paid sales
 * server-side would have to reproduce (or bypass) sales/payment/inventory/report
 * semantics, so it is deferred. The seed_demo_sales flag is accepted but recorded
 * as a deferred no-op. See Sprint 12 evidence.
 */
class DemoDataSeederService
{
    public function __construct(
        private readonly DemoCatalogFactory $catalog,
        private readonly InventoryMovementService $inventory,
    ) {}

    /**
     * @param  array{seed_products?: bool, seed_opening_inventory?: bool, seed_demo_sales?: bool}  $options
     * @return array{manifest: array<string, array<int, int>>, checklist: array<string, bool>, notes: array<int, string>}
     */
    public function seed(Tenant $tenant, Store $store, array $options = []): array
    {
        if ((int) $store->tenant_id !== (int) $tenant->id) {
            throw new RuntimeException('Store does not belong to tenant.');
        }

        $seedProducts = $options['seed_products'] ?? true;
        $seedOpening = $options['seed_opening_inventory'] ?? true;
        $seedSales = $options['seed_demo_sales'] ?? false;

        $manifest = [
            'category_ids' => [],
            'product_ids' => [],
            'price_ids' => [],
            'movement_ids' => [],
            'sale_ids' => [],
            'payment_ids' => [],
        ];
        $notes = [];

        DB::transaction(function () use ($tenant, $store, $seedProducts, $seedOpening, &$manifest): void {
            if (! $seedProducts) {
                return;
            }

            $categoriesByName = $this->seedCategories($tenant, $manifest);

            foreach ($this->catalog->products() as $definition) {
                $category = $categoriesByName[$definition['category']] ?? null;

                $product = Product::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'sku' => $definition['sku'],
                    ],
                    [
                        'store_id' => null,
                        'category_id' => $category?->id,
                        'name' => $definition['name'],
                        'unit' => $definition['unit'],
                        'cost_price' => $definition['cost_price'],
                        'selling_price' => $definition['selling_price'],
                        'is_stock_tracked' => $definition['is_stock_tracked'],
                        'is_active' => true,
                    ],
                );
                $manifest['product_ids'][] = $product->id;

                $price = ProductStorePrice::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'selling_price' => $definition['selling_price'],
                        'is_active' => true,
                    ],
                );
                $manifest['price_ids'][] = $price->id;

                if ($seedOpening && $definition['is_stock_tracked']) {
                    $movementId = $this->seedOpeningMovement($tenant, $store, $product, $definition['opening_qty']);
                    if ($movementId !== null) {
                        $manifest['movement_ids'][] = $movementId;
                    }
                }
            }
        });

        if ($seedSales) {
            // Demo sales are a deferred foundation in Sprint 12 (see class docblock).
            $notes[] = 'demo_sales_deferred';
        }

        return [
            'manifest' => $this->uniqueManifest($manifest),
            'checklist' => $this->checklistFor($manifest, $seedSales),
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, array<int, int>>  $manifest
     * @return array<string, ProductCategory>
     */
    private function seedCategories(Tenant $tenant, array &$manifest): array
    {
        $byName = [];

        foreach ($this->catalog->categories() as $index => $definition) {
            $category = ProductCategory::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'store_id' => null,
                    'name' => $definition['name'],
                ],
                [
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
            $manifest['category_ids'][] = $category->id;
            $byName[$definition['name']] = $category;
        }

        return $byName;
    }

    /**
     * Create an OPENING ledger entry once per product/store. Returns the existing
     * movement id when opening stock was already seeded (idempotent).
     */
    private function seedOpeningMovement(Tenant $tenant, Store $store, Product $product, string $qty): ?int
    {
        $existing = InventoryMovement::query()
            ->where('tenant_id', $tenant->id)
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->where('movement_type', InventoryMovement::TYPE_OPENING)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $movement = $this->inventory->createOpeningMovement(
            tenantId: (int) $tenant->id,
            storeId: (int) $store->id,
            productId: (int) $product->id,
            qty: $qty,
            notes: 'Demo opening stock',
        );

        return (int) $movement->id;
    }

    /**
     * @param  array<string, array<int, int>>  $manifest
     * @return array<string, array<int, int>>
     */
    private function uniqueManifest(array $manifest): array
    {
        foreach ($manifest as $key => $ids) {
            $manifest[$key] = array_values(array_unique($ids));
        }

        return $manifest;
    }

    /**
     * @param  array<string, array<int, int>>  $manifest
     * @return array<string, bool>
     */
    private function checklistFor(array $manifest, bool $seedSales): array
    {
        return [
            'demo_categories_seeded' => $manifest['category_ids'] !== [],
            'demo_products_seeded' => $manifest['product_ids'] !== [],
            'demo_prices_seeded' => $manifest['price_ids'] !== [],
            'opening_inventory_seeded' => $manifest['movement_ids'] !== [],
            'demo_sales_seeded' => false,
        ];
    }
}
