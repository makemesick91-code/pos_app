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
 * Sprint 8 — the manual adjustment endpoint. Proves OPENING / ADJUSTMENT_IN /
 * ADJUSTMENT_OUT work, signed_qty is backend-computed, qty must be positive,
 * SALE_OUT cannot be created manually, and store/product ownership is enforced.
 */
class InventoryAdjustmentApiTest extends TestCase
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

    public function test_user_can_create_opening_movement_with_positive_signed_qty(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'store_id' => $this->store->id,
                'product_id' => $product->id,
                'movement_type' => 'OPENING',
                'qty' => '10.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.movement_type', 'OPENING')
            ->assertJsonPath('data.signed_qty', '10.00');

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'movement_type' => 'OPENING',
            'signed_qty' => '10.00',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_create_adjustment_in(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $product->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '4.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.signed_qty', '4.50');
    }

    public function test_adjustment_out_produces_negative_signed_qty(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $product->id,
                'movement_type' => 'ADJUSTMENT_OUT',
                'qty' => '3.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.signed_qty', '-3.00');
    }

    public function test_qty_must_be_positive(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $product->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '0',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('qty');
    }

    public function test_sale_out_cannot_be_created_via_adjustment_endpoint(): void
    {
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $product->id,
                'movement_type' => 'SALE_OUT',
                'qty' => '5.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('movement_type');

        $this->assertDatabaseMissing('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_SALE_OUT,
        ]);
    }

    public function test_product_must_belong_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['code' => 'TENANT-B']);
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'product_id' => $otherProduct->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '5.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');
    }

    public function test_store_must_belong_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['code' => 'TENANT-C']);
        $otherStore = Store::factory()->create(['tenant_id' => $otherTenant->id, 'code' => 'C1']);
        $product = $this->product();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/adjustments', [
                'store_id' => $otherStore->id,
                'product_id' => $product->id,
                'movement_type' => 'ADJUSTMENT_IN',
                'qty' => '5.00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
