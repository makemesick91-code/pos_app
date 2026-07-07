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
 * Sprint 8 — a replayed offline sale (same client_reference) must not create a
 * second sale nor a duplicate SALE_OUT movement. The same reference under a
 * different tenant stays isolated.
 */
class InventoryIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'selling_price' => 10000,
        ]);
    }

    private function payload(int $productId): array
    {
        return [
            'source' => 'ANDROID_OFFLINE',
            'client_reference' => 'dup-ref-001',
            'items' => [['product_id' => $productId, 'qty' => 2]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 20000],
        ];
    }

    public function test_replay_does_not_duplicate_sale_or_sale_out(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id))
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('product_id', $product->id)
                ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)
                ->count(),
        );
    }

    public function test_same_reference_in_another_tenant_is_isolated(): void
    {
        $productA = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($productA->id))
            ->assertCreated();

        // A separate tenant reusing the same reference string creates its own
        // sale and its own SALE_OUT — no collision with tenant A.
        $tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'B1']);
        $cashierB = User::factory()->cashier()->create([
            'tenant_id' => $tenantB->id,
            'store_id' => $storeB->id,
        ]);
        $productB = Product::factory()->create(['tenant_id' => $tenantB->id, 'selling_price' => 10000]);

        $this->actingAs($cashierB, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($productB->id))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseCount('sales', 2);
        $this->assertSame(
            1,
            InventoryMovement::query()->where('tenant_id', $this->tenant->id)
                ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)->count(),
        );
        $this->assertSame(
            1,
            InventoryMovement::query()->where('tenant_id', $tenantB->id)
                ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)->count(),
        );
    }
}
