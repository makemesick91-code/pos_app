<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 6 receipt isolation gate: tenant A can never preview or print tenant B's
 * receipt. Cross-tenant access 404s (identical to a non-existent sale) so tenant A
 * cannot even infer that tenant B's sale exists.
 */
class ReceiptTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $cashierA;
    private User $cashierB;

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
    }

    private function paidSale(Tenant $tenant, Store $store, User $cashier): Sale
    {
        $sale = Sale::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'grand_total' => 20000,
            'paid_total' => 20000,
        ]);

        SaleItem::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'sale_id' => $sale->id,
            'product_name' => 'Rahasia '.$tenant->code,
        ]);

        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_PAID,
        ]);

        return $sale;
    }

    public function test_tenant_a_can_get_own_receipt(): void
    {
        $saleA = $this->paidSale($this->tenantA, $this->storeA, $this->cashierA);

        $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/sales/{$saleA->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.sale_id', $saleA->id)
            ->assertJsonPath('data.printable', true);
    }

    public function test_tenant_a_cannot_get_tenant_b_receipt(): void
    {
        $saleB = $this->paidSale($this->tenantB, $this->storeB, $this->cashierB);

        $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/sales/{$saleB->id}/receipt")
            ->assertNotFound();
    }

    public function test_tenant_a_cannot_infer_tenant_b_receipt_data(): void
    {
        $saleB = $this->paidSale($this->tenantB, $this->storeB, $this->cashierB);

        $response = $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/sales/{$saleB->id}/receipt")
            ->assertNotFound();

        // No leakage of tenant B's snapshot data via the 404 body.
        $this->assertStringNotContainsString('Rahasia TENANT-B', $response->getContent());
    }
}
