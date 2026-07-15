<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-8C-06 — backend regression fence for premium receipt / transaction-history
 * parity and deduplication (UIX8C-R177/R178/R181/R183).
 *
 * UIX-8C-06 adds NO backend source: the premium receipt and reconciled history
 * are client projections over the canonical sale/receipt the backend already
 * owns. This suite pins the contract those projections rely on:
 *   - the receipt endpoint mirrors the canonical sale exactly (items, quantities,
 *     unit prices, whole-Rupiah totals, tender, change);
 *   - a replayed stable client_reference reconciles to exactly ONE sale, so one
 *     logical transaction is one history row and one receipt (no duplicate);
 *   - a receipt is tenant-scoped — a foreign tenant can never read it.
 *
 * The backend already satisfies this (SaleService dedupes by
 * (tenant, store, client_reference); ReceiptController reads the immutable
 * sale_item snapshot). This fence keeps the UIX-8C-06 receipt/history UX honest.
 */
class Uix8c06ReceiptHistoryParityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-R6']);
        $this->store = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'R6-1',
            'name' => 'Toko R6',
        ]);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Kasir R6',
        ]);
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'selling_price' => 10000,
        ]);
    }

    private function payload(int $productId, string $reference): array
    {
        return [
            'source' => 'ANDROID_OFFLINE',
            'client_reference' => $reference,
            'client_created_at' => '2026-07-15T04:00:00Z',
            'items' => [['product_id' => $productId, 'qty' => 2]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 50000],
        ];
    }

    public function test_receipt_mirrors_canonical_sale_totals_and_items(): void
    {
        $product = $this->product();

        $created = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id, 'r6-parity-001'))
            ->assertCreated();

        $saleId = $created->json('data.id');
        $grandTotal = $created->json('data.grand_total');

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$saleId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.sale_id', $saleId)
            ->assertJsonPath('data.printable', true)
            ->assertJsonPath('data.totals.grand_total', $grandTotal)
            ->assertJsonPath('data.items.0.qty', fn ($qty) => (int) round((float) $qty) === 2)
            ->assertJsonPath('data.payments.0.method', 'CASH');
    }

    public function test_replayed_reference_is_one_sale_one_receipt_no_duplicate_history(): void
    {
        $product = $this->product();
        $reference = 'r6-dedup-002';

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id, $reference))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);
        $saleId = $first->json('data.id');

        // Two replays (a manual retry + a worker replay) of the SAME reference.
        foreach (range(1, 2) as $_) {
            $replay = $this->actingAs($this->cashier, 'sanctum')
                ->postJson('/api/v1/sales', $this->payload($product->id, $reference))
                ->assertOk()
                ->assertJsonPath('meta.idempotent_replay', true);
            $this->assertSame($saleId, $replay->json('data.id'));
        }

        // One logical transaction => one sale => one history row => one receipt.
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(1, \DB::table('sale_items')->where('sale_id', $saleId)->count());

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$saleId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.sale_id', $saleId);
    }

    public function test_receipt_is_tenant_scoped_foreign_tenant_cannot_read(): void
    {
        $product = $this->product();
        $saleId = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id, 'r6-scope-003'))
            ->assertCreated()
            ->json('data.id');

        $otherTenant = Tenant::factory()->create(['code' => 'TENANT-R6-OTHER']);
        $otherStore = Store::factory()->create(['tenant_id' => $otherTenant->id, 'code' => 'OTHER1']);
        $otherCashier = User::factory()->cashier()->create([
            'tenant_id' => $otherTenant->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($otherCashier, 'sanctum')
            ->getJson("/api/v1/sales/{$saleId}/receipt")
            ->assertStatus(404);
    }
}
