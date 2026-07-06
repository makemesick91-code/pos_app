<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local/dev seed for Sprint 2. Depends on Sprint1TenantSeeder for tenants,
 * stores and users. Seeds a tenant-isolated product catalog for Tenant A and
 * Tenant B, plus one store price override on Tenant A's store.
 *
 * FOR LOCAL/DEV/TESTING ONLY. Contains no production credentials.
 */
class Sprint2ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantA = Tenant::where('code', 'TENANT-A')->first();
        $tenantB = Tenant::where('code', 'TENANT-B')->first();

        if ($tenantA === null || $tenantB === null) {
            // Sprint 1 tenants are a prerequisite for this seed.
            return;
        }

        $storeA = Store::where('tenant_id', $tenantA->id)->where('code', 'A1')->first();
        $storeB = Store::where('tenant_id', $tenantB->id)->where('code', 'B1')->first();

        // Tenant A catalog.
        $drinksA = ProductCategory::updateOrCreate(
            ['tenant_id' => $tenantA->id, 'store_id' => null, 'name' => 'Minuman'],
            ['sort_order' => 1, 'is_active' => true],
        );
        ProductCategory::updateOrCreate(
            ['tenant_id' => $tenantA->id, 'store_id' => null, 'name' => 'Makanan'],
            ['sort_order' => 2, 'is_active' => true],
        );

        $productA1 = Product::updateOrCreate(
            ['tenant_id' => $tenantA->id, 'sku' => 'SKU-A-001'],
            [
                'store_id' => null,
                'category_id' => $drinksA->id,
                'barcode' => '8990000000011',
                'name' => 'Kopi Susu',
                'unit' => 'pcs',
                'cost_price' => 8000,
                'selling_price' => 15000,
                'is_stock_tracked' => true,
                'is_active' => true,
            ],
        );
        Product::updateOrCreate(
            ['tenant_id' => $tenantA->id, 'sku' => 'SKU-A-002'],
            [
                'store_id' => null,
                'category_id' => $drinksA->id,
                'barcode' => '8990000000028',
                'name' => 'Teh Manis',
                'unit' => 'pcs',
                'cost_price' => 3000,
                'selling_price' => 8000,
                'is_stock_tracked' => true,
                'is_active' => true,
            ],
        );

        if ($storeA !== null) {
            ProductStorePrice::updateOrCreate(
                ['tenant_id' => $tenantA->id, 'store_id' => $storeA->id, 'product_id' => $productA1->id],
                ['selling_price' => 14000, 'is_active' => true],
            );
        }

        // Tenant B catalog (isolated).
        $catB = ProductCategory::updateOrCreate(
            ['tenant_id' => $tenantB->id, 'store_id' => null, 'name' => 'Produk Tenant B'],
            ['sort_order' => 1, 'is_active' => true],
        );
        Product::updateOrCreate(
            ['tenant_id' => $tenantB->id, 'sku' => 'SKU-B-001'],
            [
                'store_id' => $storeB?->id,
                'category_id' => $catB->id,
                'barcode' => '8991111111114',
                'name' => 'Produk B Satu',
                'unit' => 'pcs',
                'cost_price' => 5000,
                'selling_price' => 12000,
                'is_stock_tracked' => true,
                'is_active' => true,
            ],
        );
    }
}
