<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 7 — duplicate offline submits (network retries replaying the same
 * client-generated reference) must never create a second sale.
 */
class SalesIdempotencyTest extends TestCase
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

    private function payload(int $productId, array $overrides = []): array
    {
        return array_merge([
            'source' => 'ANDROID_OFFLINE',
            'client_reference' => 'dup-ref-001',
            'items' => [['product_id' => $productId, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ], $overrides);
    }

    public function test_duplicate_submit_does_not_create_duplicate_sale(): void
    {
        $product = $this->product();

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id))
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $second = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id))
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        // Same sale returned, exactly one row persisted.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.invoice_number'), $second->json('data.invoice_number'));
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_same_reference_with_different_payload_is_safe(): void
    {
        $product = $this->product(['selling_price' => 10000]);
        $other = $this->product(['selling_price' => 50000]);

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($product->id))
            ->assertCreated();

        // A retry that (buggily) carries a different cart but the same reference
        // must still resolve to the original sale — no second sale, no overwrite.
        $second = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($other->id, [
                'items' => [['product_id' => $other->id, 'qty' => 5]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 250000],
            ]))
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.grand_total'), $second->json('data.grand_total'));
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_same_reference_across_tenants_does_not_collide(): void
    {
        $productA = $this->product();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $this->payload($productA->id))
            ->assertCreated();

        // A completely separate tenant reusing the same reference string.
        $tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'B1']);
        $cashierB = User::factory()->cashier()->create([
            'tenant_id' => $tenantB->id,
            'store_id' => $storeB->id,
        ]);
        $productB = Product::factory()->create([
            'tenant_id' => $tenantB->id,
            'selling_price' => 10000,
        ]);

        $this->actingAs($cashierB, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'dup-ref-001',
                'items' => [['product_id' => $productB->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        // Both tenants own a distinct sale under the shared reference string.
        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'client_reference' => 'dup-ref-001',
        ]);
        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenantB->id,
            'client_reference' => 'dup-ref-001',
        ]);
    }

    public function test_online_sales_without_reference_are_never_deduplicated(): void
    {
        $product = $this->product();

        $payload = [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ];

        $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/sales', $payload)->assertCreated();
        $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/sales', $payload)->assertCreated();

        // Two independent online sales, both with a null reference, coexist.
        $this->assertDatabaseCount('sales', 2);
    }

    /**
     * UIX7-R054/R055 — the online cashier now carries a stable client_reference
     * so a retry after a lost response (a read timeout AFTER the server already
     * committed the sale) is deduped rather than creating a second sale. This is
     * the end-to-end contract the Android online-checkout fix relies on: the same
     * dedup that has always protected the offline replay must also protect an
     * online sale that opts in with a reference.
     */
    public function test_online_cash_sale_with_reference_is_idempotent_on_retry(): void
    {
        $product = $this->product();

        $payload = [
            'source' => 'ANDROID_ONLINE',
            'client_reference' => 'online-ref-timeout-retry-001',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
        ];

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        // The lost-response retry re-uses the exact same reference.
        $second = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/sales', $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('data.invoice_number'), $second->json('data.invoice_number'));
        $this->assertSame($first->json('data.grand_total'), $second->json('data.grand_total'));
        // Exactly one financial transaction persisted for the online retry.
        $this->assertDatabaseCount('sales', 1);
    }
}
