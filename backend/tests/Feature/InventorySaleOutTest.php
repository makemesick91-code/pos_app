<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 8 — a successful sale decrements stock through the ledger. SALE_OUT is
 * created for stock-tracked products only, references the sale item, and uses
 * the backend sale item snapshot (never Android totals).
 */
class InventorySaleOutTest extends TestCase
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

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produk A',
            'selling_price' => 10000,
        ], $overrides));
    }

    public function test_paid_cash_sale_creates_sale_out_movement(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 3]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 30000],
            ])
            ->assertCreated();

        $item = SaleItem::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'movement_type' => InventoryMovement::TYPE_SALE_OUT,
            'signed_qty' => '-3.00',
            'reference_type' => InventoryMovement::REFERENCE_SALE_ITEM,
            'reference_id' => $item->id,
            'source' => InventoryMovement::SOURCE_SALE,
        ]);
    }

    public function test_offline_cash_sync_creates_sale_out_movement(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'offline-ref-1',
                'items' => [['product_id' => $product->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 20000],
            ])
            ->assertCreated();

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('product_id', $product->id)
                ->where('movement_type', InventoryMovement::TYPE_SALE_OUT)
                ->count(),
        );
    }

    public function test_non_stock_tracked_product_does_not_create_sale_out(): void
    {
        $product = $this->product(['is_stock_tracked' => false]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 5]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 50000],
            ])
            ->assertCreated();

        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $product->id,
            'movement_type' => InventoryMovement::TYPE_SALE_OUT,
        ]);
    }

    public function test_sale_item_snapshot_is_preserved(): void
    {
        $product = $this->product(['name' => 'Nama Asli']);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertCreated();

        // Renaming the catalog product later must not change the snapshot.
        $product->update(['name' => 'Nama Baru']);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'product_name' => 'Nama Asli',
        ]);
    }
}
