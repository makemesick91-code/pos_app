<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-8C-05 — backend regression fence for the premium payment / offline-sync
 * RECOVERY UX (UIX8C-R118..R123, R157..R160).
 *
 * UIX-8C-05 adds NO backend source: it reuses the UIX-8C-04 transaction
 * foundation (stable client_reference, atomic durable persistence, WorkManager
 * replay) and the backend's existing dedupe by (tenant, store, client_reference).
 * What is NEW on the client is the recovery UX — a governed MANUAL retry and a
 * reconnect signal that can re-trigger the canonical sync. This suite pins the
 * contract that UX relies on: however many times the SAME stable reference is
 * replayed (repeated manual-retry taps + a reconnect-driven worker replay racing
 * each other), the backend still reconciles to exactly ONE canonical sale, ONE
 * payment, ONE item set, and never a duplicate inventory movement. A manual retry
 * must never mint a second logical transaction.
 *
 * The backend already satisfies this (SaleService pre-checks by
 * (tenant, store, client_reference) behind a unique index); this is the regression
 * fence that keeps the UIX-8C-05 recovery UX safe.
 */
class PaymentSyncUxIdempotencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-UX']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'UX1']);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'selling_price' => 15000,
        ]);
    }

    private function offlinePayload(int $productId, string $reference): array
    {
        return [
            'source' => 'ANDROID_OFFLINE',
            'client_reference' => $reference,
            'client_created_at' => '2026-07-15T04:00:00Z',
            'items' => [['product_id' => $productId, 'qty' => 2]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 50000],
        ];
    }

    /**
     * Repeated manual-retry taps (the operator pressing "Coba Sinkron Lagi"
     * several times) plus a reconnect-driven worker replay must all reconcile to
     * one canonical transaction — never a second sale/payment/item set/inventory
     * movement (UIX8C-R119..R122, R157..R159).
     */
    public function test_repeated_manual_retry_replays_reconcile_to_one_transaction(): void
    {
        $product = $this->product();
        $reference = 'uix8c05-manual-retry-ref-001';

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $saleId = $first->json('data.id');
        $inventoryAfterFirst = \DB::table('inventory_movements')->count();

        // Five replays of the SAME reference (four manual-retry taps + one worker
        // replay): every one is an idempotent replay of the same sale.
        foreach (range(1, 5) as $_) {
            $replay = $this->actingAs($this->cashier, 'sanctum')
                ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
                ->assertOk()
                ->assertJsonPath('meta.idempotent_replay', true);
            $this->assertSame($saleId, $replay->json('data.id'));
        }

        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(1, \DB::table('payments')->where('sale_id', $saleId)->count());
        $this->assertSame(1, \DB::table('sale_items')->where('sale_id', $saleId)->count());
        $this->assertSame($inventoryAfterFirst, \DB::table('inventory_movements')->count());
    }

    /**
     * The whole-Rupiah money contract is stable across every replay: the grand
     * total returned to the device is identical on the original create and on each
     * recovery-UX replay (UIX8C-R121/R136).
     */
    public function test_money_is_stable_across_recovery_replays(): void
    {
        $product = $this->product();
        $reference = 'uix8c05-money-stable-ref';

        $created = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertCreated();

        $grandTotal = $created->json('data.grand_total');

        $replay = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->offlinePayload($product->id, $reference))
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertSame($grandTotal, $replay->json('data.grand_total'));
        $this->assertDatabaseCount('sales', 1);
    }
}
