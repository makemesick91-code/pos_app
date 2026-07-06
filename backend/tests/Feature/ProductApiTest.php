<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->ownerA = User::factory()->tenantOwner()->create(['tenant_id' => $this->tenantA->id]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SKU-001',
            'name' => 'Produk A',
            'selling_price' => 10000,
        ], $overrides);
    }

    public function test_tenant_user_can_create_product(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-001')
            ->assertJsonPath('data.unit', 'pcs');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenantA->id,
            'sku' => 'SKU-001',
        ]);
    }

    public function test_sku_unique_per_tenant(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-001']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload(['sku' => 'SKU-001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    public function test_same_sku_allowed_across_different_tenants(): void
    {
        // Tenant B already owns SKU-001.
        Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-001']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload(['sku' => 'SKU-001']))
            ->assertCreated();
    }

    public function test_barcode_unique_per_tenant_when_provided(): void
    {
        Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'sku' => 'SKU-EXIST',
            'barcode' => '8990000000011',
        ]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload(['barcode' => '8990000000011']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_tenant_user_can_list_and_search_own_products(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A', 'name' => 'Kopi']);
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-B', 'name' => 'Teh']);
        Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-A', 'name' => 'Kopi B']);

        $all = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertOk();
        $this->assertCount(2, $all->json('data'));

        $search = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/products?q=Kopi')
            ->assertOk();
        $this->assertCount(1, $search->json('data'));
        $this->assertSame('Kopi', $search->json('data.0.name'));
    }

    public function test_tenant_user_can_update_own_product(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-U']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", ['name' => 'Renamed', 'selling_price' => 25000])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_deleting_product_sets_inactive(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-D']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_category_must_belong_to_tenant(): void
    {
        $foreignCategory = ProductCategory::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload(['category_id' => $foreignCategory->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_store_id_must_belong_to_tenant(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/products', $this->validPayload(['store_id' => $this->storeB->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
