<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 8 — current stock is derived from the signed sum of inventory
 * movements. No movement means zero; stock is store-scoped; listings only ever
 * return the tenant's own products.
 */
class CurrentStockApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
        ], $overrides));
    }

    private function movement(Product $product, string $type, string $signedQty, ?int $storeId = null): void
    {
        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $storeId ?? $this->store->id,
            'product_id' => $product->id,
            'movement_type' => $type,
            'qty' => ltrim($signedQty, '-'),
            'signed_qty' => $signedQty,
        ]);
    }

    public function test_current_stock_is_sum_of_signed_qty(): void
    {
        $product = $this->product();
        $this->movement($product, InventoryMovement::TYPE_OPENING, '10.00');
        $this->movement($product, InventoryMovement::TYPE_ADJUSTMENT_IN, '5.00');
        $this->movement($product, InventoryMovement::TYPE_ADJUSTMENT_OUT, '-3.00');

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/products/{$product->id}/stock")
            ->assertOk()
            ->assertJsonPath('data.current_stock', '12.00');
    }

    public function test_product_without_movement_has_zero_stock(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/products/{$product->id}/stock")
            ->assertOk()
            ->assertJsonPath('data.current_stock', '0.00');
    }

    public function test_stock_is_store_scoped(): void
    {
        $product = $this->product();
        $otherStore = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A2']);

        // Movement in the other store must not count toward the context store.
        $this->movement($product, InventoryMovement::TYPE_OPENING, '99.00', $otherStore->id);
        $this->movement($product, InventoryMovement::TYPE_OPENING, '7.00');

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/products/{$product->id}/stock")
            ->assertOk()
            ->assertJsonPath('data.current_stock', '7.00');
    }

    public function test_current_stock_list_returns_only_tenant_products(): void
    {
        $mine = $this->product(['name' => 'Kopi Susu']);
        $this->movement($mine, InventoryMovement::TYPE_OPENING, '8.00');

        $otherTenant = Tenant::factory()->create(['code' => 'TENANT-B']);
        Product::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Teh Lawan']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk()
            ->assertJsonPath('meta.tenant_id', $this->tenant->id);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Kopi Susu'));
        $this->assertFalse($names->contains('Teh Lawan'));

        $row = collect($response->json('data'))->firstWhere('product_id', $mine->id);
        $this->assertSame('8.00', $row['current_stock']);
    }

    public function test_non_stock_tracked_product_still_reports_stock_field(): void
    {
        // Non-stock-tracked products never accrue SALE_OUT, so they read 0 and
        // are flagged is_stock_tracked=false for the UI to decide visibility.
        $product = $this->product(['is_stock_tracked' => false]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/inventory/products/{$product->id}/stock")
            ->assertOk()
            ->assertJsonPath('data.is_stock_tracked', false)
            ->assertJsonPath('data.current_stock', '0.00');
    }
}
