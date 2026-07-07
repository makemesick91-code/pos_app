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
 * Sprint 9 — inventory movement summary is derived from the inventory_movements
 * ledger (never a mutable product stock column), grouped by movement_type with
 * backend-computed signed_qty totals.
 */
class InventoryMovementSummaryReportApiTest extends TestCase
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

    private function movement(string $type, string $signedQty, ?Store $store = null): void
    {
        $store ??= $this->store;
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'movement_type' => $type,
            'qty' => ltrim($signedQty, '-'),
            'signed_qty' => $signedQty,
        ]);
    }

    public function test_movements_are_summarized_by_type_with_signed_totals(): void
    {
        $this->movement(InventoryMovement::TYPE_SALE_OUT, '-10.00');
        $this->movement(InventoryMovement::TYPE_SALE_OUT, '-15.00');
        $this->movement(InventoryMovement::TYPE_ADJUSTMENT_IN, '10.00');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/inventory-movements-summary')
            ->assertOk();

        $rows = collect($response->json('data'));

        $saleOut = $rows->firstWhere('movement_type', InventoryMovement::TYPE_SALE_OUT);
        $this->assertSame(2, $saleOut['movement_count']);
        $this->assertSame('25.00', $saleOut['qty_total']);
        $this->assertSame('-25.00', $saleOut['signed_qty_total']);

        $adjIn = $rows->firstWhere('movement_type', InventoryMovement::TYPE_ADJUSTMENT_IN);
        $this->assertSame('10.00', $adjIn['signed_qty_total']);
    }

    public function test_summary_is_store_scoped(): void
    {
        $otherStore = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A2']);
        $this->movement(InventoryMovement::TYPE_SALE_OUT, '-5.00');
        $this->movement(InventoryMovement::TYPE_SALE_OUT, '-99.00', $otherStore);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/inventory-movements-summary?store_id='.$this->store->id)
            ->assertOk();

        $rows = collect($response->json('data'));
        $saleOut = $rows->firstWhere('movement_type', InventoryMovement::TYPE_SALE_OUT);
        $this->assertSame(1, $saleOut['movement_count']);
        $this->assertSame('5.00', $saleOut['qty_total']);
    }
}
