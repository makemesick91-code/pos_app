<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 7 — offline sync must not become a tenant-isolation bypass. Tenant A
 * can never reach tenant B's store, product, or sale through an offline submit,
 * and can never exploit a shared client_reference to touch tenant B's data.
 */
class OfflineSalesTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $cashierA;
    private User $cashierB;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->cashierA = User::factory()->cashier()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
        ]);
        $this->cashierB = User::factory()->cashier()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
        ]);
        $this->productB = Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'sku' => 'SKU-B',
            'selling_price' => 10000,
        ]);
    }

    public function test_tenant_a_cannot_offline_sync_using_tenant_b_store(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A']);

        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'a-offline-1',
                'store_id' => $this->storeB->id,
                'items' => [['product_id' => $productA->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_tenant_a_cannot_offline_sync_tenant_b_product(): void
    {
        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'a-offline-2',
                'items' => [['product_id' => $this->productB->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_tenant_a_cannot_see_tenant_b_offline_sale(): void
    {
        $productB2 = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'selling_price' => 10000]);

        $saleBId = $this->actingAs($this->cashierB, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'b-offline-1',
                'items' => [['product_id' => $productB2->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertCreated()
            ->json('data.id');

        // Direct fetch is a 404, and tenant A's list never contains it.
        $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/sales/{$saleBId}")
            ->assertNotFound();

        $list = $this->actingAs($this->cashierA, 'sanctum')
            ->getJson('/api/v1/sales')
            ->assertOk();

        $this->assertCount(0, $list->json('data'));
    }

    public function test_tenant_a_cannot_exploit_client_reference_to_reach_tenant_b_sale(): void
    {
        $productB2 = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'selling_price' => 10000]);
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'selling_price' => 10000]);

        // Tenant B creates an offline sale with a known reference.
        $saleB = $this->actingAs($this->cashierB, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'shared-ref',
                'items' => [['product_id' => $productB2->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertCreated();

        // Tenant A submits the SAME reference: it must get its own brand-new sale,
        // never tenant B's, and never a replay.
        $saleA = $this->actingAs($this->cashierA, 'sanctum')
            ->postJson('/api/v1/sales', [
                'source' => 'ANDROID_OFFLINE',
                'client_reference' => 'shared-ref',
                'items' => [['product_id' => $productA->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->assertNotSame($saleB->json('data.id'), $saleA->json('data.id'));

        $this->assertSame(
            $this->tenantA->id,
            Sale::find($saleA->json('data.id'))->tenant_id
        );
        $this->assertSame(
            $this->tenantB->id,
            Sale::find($saleB->json('data.id'))->tenant_id
        );
    }
}
