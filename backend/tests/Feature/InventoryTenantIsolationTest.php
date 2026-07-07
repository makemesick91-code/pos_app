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
 * Sprint 8 isolation gate: tenant A can never read or write tenant B's stock,
 * movements, products, or stores through any inventory endpoint.
 */
class InventoryTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $userA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);
        $this->productB = Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Produk B',
        ]);

        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'product_id' => $this->productB->id,
            'movement_type' => InventoryMovement::TYPE_OPENING,
            'qty' => '50.00',
            'signed_qty' => '50.00',
        ]);
    }

    public function test_tenant_a_cannot_view_tenant_b_product_stock(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/inventory/products/{$this->productB->id}/stock")
            ->assertNotFound();
    }

    public function test_tenant_a_current_stock_list_excludes_tenant_b_products(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('product_id');
        $this->assertFalse($ids->contains($this->productB->id));
    }

    public function test_tenant_a_movements_list_excludes_tenant_b_movements(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/inventory/movements')
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_tenant_a_cannot_adjust_tenant_b_product(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $this->productB->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '5.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_tenant_a_cannot_adjust_using_tenant_b_store(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'store_id' => $this->storeB->id,
                'product_id' => $productA->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '5.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
