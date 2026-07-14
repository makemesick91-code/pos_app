<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-8C-04 — backend idempotency proof for the durable offline CASH fix.
 *
 * The Android fix persists an offline CASH sale locally and later replays it with
 * a STABLE client_reference (the same key across process restart, reconnect, and
 * WorkManager replay). This suite pins the backend contract the device relies on:
 * a replay of the same tenant-scoped reference reconciles to exactly ONE canonical
 * sale, ONE payment, ONE set of sale items, and does NOT duplicate inventory
 * movements — including the most dangerous case, a timeout AFTER the server has
 * already committed (UIX8C-R116/R118..R122).
 *
 * The backend already satisfies this (SaleService::createCashSale pre-checks by
 * (tenant, store, client_reference) and is backed by a unique index); these tests
 * are the regression fence that keeps it true.
 */
class OfflineCashDurabilityIdempotencyTest extends TestCase
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
            'selling_price' => 15000,
        ], $overrides));
    }

    private function offlinePayload(int $productId, string $reference, int $qty = 2): array
    {
        return [
            'source' => 'ANDROID_OFFLINE',
            'client_reference' => $reference,
            'client_created_at' => '2026-07-15T03:00:00Z',
            'items' => [['product_id' => $productId, 'qty' => $qty]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 50000],
        ];
    }

    /**
     * The core R11 recovery contract: a durable offline sale replayed on reconnect
     * (and again by the worker) produces exactly one sale / payment / item set and
     * never duplicates inventory — UIX8C-R116/R119..R122.
     */
    public function test_stable_reference_replay_creates_exactly_one_sale_payment_items_inventory(): void
    {
        $product = $this->product();
        $reference = 'offline-durable-ref-8c04-001';

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $saleId = $first->json('data.id');
        $inventoryAfterFirst = \DB::table('inventory_movements')->count();

        // Reconnect + WorkManager replay: the SAME reference is submitted twice more.
        foreach ([1, 2] as $_) {
            $replay = $this->actingAs($this->cashier, 'sanctum')
                ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
                ->assertOk()
                ->assertJsonPath('meta.idempotent_replay', true);
            $this->assertSame($saleId, $replay->json('data.id'));
        }

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(1, \DB::table('payments')->where('sale_id', $saleId)->count());
        $this->assertSame(1, \DB::table('sale_items')->where('sale_id', $saleId)->count());
        // Inventory movements are unchanged by the replays (no double decrement).
        $this->assertSame($inventoryAfterFirst, \DB::table('inventory_movements')->count());
    }

    /**
     * Timeout-after-server-commit: the device never saw the 201, queued the sale,
     * and retries the same reference. The backend must reconcile to the existing
     * sale, not create a second one (UIX8C-R118).
     */
    public function test_timeout_after_commit_retry_reconciles_to_the_same_sale(): void
    {
        $product = $this->product();
        $reference = 'offline-timeout-after-commit-8c04';

        // "First" request committed on the server (the client's response was lost).
        $committed = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertCreated();

        // The client, unaware, retries the exact same reference.
        $retry = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertSame($committed->json('data.id'), $retry->json('data.id'));
        $this->assertSame($committed->json('data.grand_total'), $retry->json('data.grand_total'));
        $this->assertDatabaseCount('sales', 1);
    }

    /**
     * Cross-tenant safety: tenant A's stable reference must NEVER resolve or
     * reconcile tenant B's transaction (UIX8C-R118 tenant scope).
     */
    public function test_cross_tenant_reference_reuse_is_isolated(): void
    {
        $productA = $this->product();
        $reference = 'shared-string-ref-8c04';

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($productA->id, $reference))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'B1']);
        $cashierB = User::factory()->cashier()->create([
            'tenant_id' => $tenantB->id,
            'store_id' => $storeB->id,
        ]);
        $productB = Product::factory()->create(['tenant_id' => $tenantB->id, 'selling_price' => 15000]);

        // Same reference string in tenant B → a fresh sale, never a replay of A's.
        $this->actingAs($cashierB, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => $reference,
                'items' => [['product_id' => $productB->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 50000],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseHas('sales', ['tenant_id' => $this->tenant->id, 'client_reference' => $reference]);
        $this->assertDatabaseHas('sales', ['tenant_id' => $tenantB->id, 'client_reference' => $reference]);
    }

    /**
     * A canonical rejection stays a rejection: an offline payload naming a product
     * from another tenant is a validation failure and NEVER a persisted sale — the
     * server contract that keeps a canonical rejection from becoming offline
     * "success" (UIX8C-R099/R101).
     */
    public function test_offline_payload_with_foreign_product_is_rejected_and_persists_nothing(): void
    {
        $tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $foreignProduct = Product::factory()->create(['tenant_id' => $tenantB->id, 'selling_price' => 15000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($foreignProduct->id, 'foreign-product-ref-8c04'))
            ->assertStatus(422);

        $this->assertDatabaseCount('sales', 0);
    }
}
