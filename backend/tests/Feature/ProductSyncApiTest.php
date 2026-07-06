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

class ProductSyncApiTest extends TestCase
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

    public function test_sync_products_returns_tenant_own_products_only(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A', 'name' => 'Mine']);
        Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-B', 'name' => 'Theirs']);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/sync/products')
            ->assertOk()
            ->assertJsonPath('meta.tenant_id', $this->tenantA->id)
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Theirs'));
    }

    public function test_sync_categories_returns_tenant_own_categories_only(): void
    {
        ProductCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Mine']);
        ProductCategory::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Theirs']);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/sync/categories')
            ->assertOk()
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Theirs'));
    }

    public function test_sync_includes_global_and_selected_store_products(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'store_id' => null, 'sku' => 'G', 'name' => 'Global']);
        Product::factory()->create(['tenant_id' => $this->tenantA->id, 'store_id' => $this->storeA->id, 'sku' => 'S', 'name' => 'StoreOnly']);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson("/api/v1/sync/products?store_id={$this->storeA->id}")
            ->assertOk()
            ->assertJsonPath('meta.store_id', $this->storeA->id);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Global'));
        $this->assertTrue($names->contains('StoreOnly'));
    }

    public function test_sync_applies_effective_selling_price_from_active_override(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => null,
            'sku' => 'SKU-P',
            'selling_price' => 10000,
        ]);
        ProductStorePrice::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'product_id' => $product->id,
            'selling_price' => 9500,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson("/api/v1/sync/products?store_id={$this->storeA->id}")
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('sku', 'SKU-P');
        $this->assertSame('10000.00', $row['selling_price']);
        $this->assertSame('9500.00', $row['effective_selling_price']);
    }

    public function test_sync_supports_updated_since(): void
    {
        $old = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'OLD', 'name' => 'Old']);
        $new = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'NEW', 'name' => 'New']);

        // Push the old product's updated_at into the past without touching timestamps.
        Product::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(10)]);

        $since = now()->subDay()->toIso8601String();

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/sync/products?updated_since='.urlencode($since))
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('New'));
        $this->assertFalse($names->contains('Old'));
    }

    public function test_tenant_user_cannot_sync_other_tenant_store_id(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->getJson("/api/v1/sync/products?store_id={$this->storeB->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
