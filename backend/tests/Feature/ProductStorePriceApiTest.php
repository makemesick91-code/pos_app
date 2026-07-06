<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStorePrice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStorePriceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $ownerA;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->ownerA = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenantA->id]);
        $this->productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A']);
        $this->productB = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-B']);
    }

    public function test_tenant_user_can_create_store_price_override(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'selling_price' => 9500,
            ])
            ->assertCreated()
            ->assertJsonPath('data.selling_price', '9500.00');

        $this->assertDatabaseHas('product_store_prices', [
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
        ]);
    }

    public function test_store_price_requires_own_tenant_store(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeB->id,
                'product_id' => $this->productA->id,
                'selling_price' => 9500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_product_must_belong_to_same_tenant(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productB->id,
                'selling_price' => 9500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_unique_tenant_store_product_enforced(): void
    {
        ProductStorePrice::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
        ]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/product-store-prices', [
                'store_id' => $this->storeA->id,
                'product_id' => $this->productA->id,
                'selling_price' => 9500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_tenant_user_can_update_own_store_price(): void
    {
        $price = ProductStorePrice::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
        ]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->putJson("/api/v1/product-store-prices/{$price->id}", ['selling_price' => 7777])
            ->assertOk()
            ->assertJsonPath('data.selling_price', '7777.00');
    }

    public function test_delete_sets_inactive(): void
    {
        $price = ProductStorePrice::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'product_id' => $this->productA->id,
        ]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->deleteJson("/api/v1/product-store-prices/{$price->id}")
            ->assertOk();

        $this->assertDatabaseHas('product_store_prices', ['id' => $price->id, 'is_active' => false]);
    }
}
