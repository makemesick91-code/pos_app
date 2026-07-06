<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Sprint 2 isolation gate: tenant A can never read, update, delete, or
 * borrow tenant B's product, category, store, or price data.
 */
class ProductTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $ownerA;
    private Product $productB;
    private ProductCategory $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->ownerA = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenantA->id]);
        $this->productB = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-B']);
        $this->categoryB = ProductCategory::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    public function test_tenant_a_cannot_show_tenant_b_product(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->getJson("/api/v1/products/{$this->productB->id}")
            ->assertNotFound();
    }

    public function test_tenant_a_cannot_update_tenant_b_product(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->putJson("/api/v1/products/{$this->productB->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertDatabaseMissing('products', ['id' => $this->productB->id, 'name' => 'Hijacked']);
    }

    public function test_tenant_a_cannot_delete_tenant_b_product(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->deleteJson("/api/v1/products/{$this->productB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $this->productB->id, 'is_active' => true]);
    }

    public function test_tenant_a_cannot_assign_tenant_b_category(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', [
                'sku' => 'SKU-A-1',
                'name' => 'Produk A',
                'selling_price' => 10000,
                'category_id' => $this->categoryB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_tenant_a_cannot_assign_tenant_b_store(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', [
                'sku' => 'SKU-A-2',
                'name' => 'Produk A',
                'selling_price' => 10000,
                'store_id' => $this->storeB->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_tenant_a_cannot_create_store_price_for_tenant_b_store_or_product(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A-3']);

        // Foreign store.
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeB->id,
                'product_id' => $productA->id,
                'selling_price' => 5000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');

        // Foreign product.
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productB->id,
                'selling_price' => 5000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }
}
