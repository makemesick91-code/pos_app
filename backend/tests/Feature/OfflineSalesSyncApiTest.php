<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 7 — an Android device replays a CASH sale it rang up while offline.
 * The backend accepts it, stamps it as offline/synced, and stays the sole
 * authority for totals and product snapshots.
 */
class OfflineSalesSyncApiTest extends TestCase
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

    public function test_offline_cash_sale_is_accepted_with_client_reference(): void
    {
        $product = $this->product(['name' => 'Kopi', 'selling_price' => 15000]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'offline-uuid-001',
                'client_created_at' => '2026-07-07T10:00:00Z',
                'items' => [['product_id' => $product->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 30000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'ANDROID_OFFLINE')
            ->assertJsonPath('data.sync_status', 'SYNCED')
            ->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.client_reference', 'offline-uuid-001')
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertNotNull($response->json('data.synced_at'));

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'client_reference' => 'offline-uuid-001',
            'source' => 'ANDROID_OFFLINE',
            'sync_status' => 'SYNCED',
        ]);
    }

    public function test_backend_recalculates_totals_and_snapshots_for_offline_sale(): void
    {
        $product = $this->product(['name' => 'Teh', 'selling_price' => 12000]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'offline-uuid-002',
                'items' => [['product_id' => $product->id, 'qty' => 3]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 40000],
                // Forged totals from a tampered device — must be rejected outright.
                'grand_total' => 1,
                'subtotal' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grand_total', 'subtotal']);

        // Now a clean offline submit: backend computes 3 * 12000 = 36000.
        $ok = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'offline-uuid-002',
                'items' => [['product_id' => $product->id, 'qty' => 3]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 40000],
            ])
            ->assertCreated()
            ->assertJsonPath('data.grand_total', '36000.00')
            ->assertJsonPath('data.items.0.product_name', 'Teh')
            ->assertJsonPath('data.items.0.unit_price', '12000.00');

        // Later catalog edits must not rewrite the snapshot.
        $product->update(['name' => 'Renamed', 'selling_price' => 99999]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $ok->json('data.id'),
            'product_name' => 'Teh',
            'unit_price' => '12000.00',
        ]);
    }

    public function test_offline_source_with_qris_is_rejected(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'offline-uuid-003',
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payment' => ['method' => 'QRIS', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment.method');

        $this->assertDatabaseCount('sales', 0);
    }
}
