<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStorePrice;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
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
            'selling_price' => 10000,
        ], $overrides));
    }

    public function test_cashier_can_create_cash_paid_sale(): void
    {
        $product = $this->product(['name' => 'Produk A', 'selling_price' => 10000]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 25000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.sync_status', 'SYNCED')
            ->assertJsonPath('data.source', 'ANDROID_ONLINE')
            ->assertJsonPath('data.grand_total', '20000.00')
            ->assertJsonPath('data.paid_total', '25000.00')
            ->assertJsonPath('data.change_total', '5000.00')
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');

        $this->assertStringStartsWith('POS-A1-', $response->json('data.invoice_number'));
        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'payment_status' => 'PAID',
        ]);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $response->json('data.id'),
            'method' => 'CASH',
            'provider' => 'MANUAL',
            'status' => 'PAID',
        ]);
    }

    public function test_backend_recalculates_totals_and_ignores_client_totals(): void
    {
        $product = $this->product(['selling_price' => 10000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 3]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 30000],
                // Forged totals — must be rejected outright.
                'grand_total' => 1,
                'subtotal' => 1,
                'paid_total' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grand_total', 'subtotal', 'paid_total']);
    }

    public function test_client_cannot_assign_tenant_or_invoice(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
                'tenant_id' => 999,
                'invoice_number' => 'HACK-1',
                'cashier_id' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id', 'invoice_number', 'cashier_id']);
    }

    public function test_sale_items_snapshot_product_name_and_unit_price(): void
    {
        $product = $this->product(['name' => 'Kopi Susu', 'selling_price' => 15000]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 30000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_name', 'Kopi Susu')
            ->assertJsonPath('data.items.0.unit_price', '15000.00')
            ->assertJsonPath('data.items.0.subtotal', '30000.00');

        // Renaming/repricing the product later must NOT rewrite the snapshot.
        $product->update(['name' => 'Renamed', 'selling_price' => 99999]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $response->json('data.id'),
            'product_name' => 'Kopi Susu',
            'unit_price' => '15000.00',
        ]);
    }

    public function test_store_price_override_is_used_when_active(): void
    {
        $product = $this->product(['selling_price' => 10000]);
        ProductStorePrice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'product_id' => $product->id,
            'selling_price' => 8000,
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 8000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '8000.00')
            ->assertJsonPath('data.grand_total', '8000.00');
    }

    public function test_paid_amount_must_cover_grand_total(): void
    {
        $product = $this->product(['selling_price' => 10000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 5000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment.paid_amount');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_line_discount_reduces_totals(): void
    {
        $product = $this->product(['selling_price' => 10000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 2, 'discount' => 5000]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 20000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '20000.00')
            ->assertJsonPath('data.discount_total', '5000.00')
            ->assertJsonPath('data.grand_total', '15000.00')
            ->assertJsonPath('data.change_total', '5000.00');
    }

    public function test_cannot_checkout_inactive_product(): void
    {
        $product = $this->product(['is_active' => false]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_list_returns_only_own_tenant_sales(): void
    {
        $product = $this->product();
        $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ])->assertCreated();

        // A foreign sale that must never appear.
        Sale::factory()->create(['store_id' => Store::factory()]);

        $list = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v1/sales')
            ->assertOk();

        $this->assertCount(1, $list->json('data'));
        $this->assertSame($this->tenant->id, Sale::find($list->json('data.0.id'))->tenant_id);
    }

    public function test_show_includes_items_and_payments(): void
    {
        $product = $this->product();
        $id = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ])->json('data.id');

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(1, 'data.payments');
    }

    public function test_cancel_sets_cancelled_status(): void
    {
        $product = $this->product();
        $id = $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ])->json('data.id');

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'CANCELLED');

        $this->assertDatabaseHas('sales', [
            'id' => $id,
            'payment_status' => 'CANCELLED',
            'cancelled_by' => $this->cashier->id,
        ]);

        // Cannot cancel twice.
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$id}/cancel")
            ->assertStatus(422);
    }

    public function test_checkout_requires_store_context(): void
    {
        $storeless = User::factory()->tenantOwner()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => null,
        ]);
        $product = $this->product();

        $this->actingAs($storeless, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }
}
