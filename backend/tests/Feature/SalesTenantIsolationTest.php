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
 * The Sprint 4 isolation gate: tenant A can never read, cancel, pay, or build a
 * sale from tenant B's sales, products, or stores.
 */
class SalesTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $cashierA;
    private Sale $saleB;
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
        $cashierB = User::factory()->cashier()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
        ]);
        $this->productB = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SKU-B']);
        $this->saleB = Sale::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'cashier_id' => $cashierB->id,
            'grand_total' => 10000,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
        ]);
    }

    public function test_tenant_a_cannot_show_tenant_b_sale(): void
    {
        $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/sales/{$this->saleB->id}")
            ->assertNotFound();
    }

    public function test_tenant_a_cannot_cancel_tenant_b_sale(): void
    {
        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson("/api/v1/sales/{$this->saleB->id}/cancel")
            ->assertNotFound();

        $this->assertDatabaseHas('sales', [
            'id' => $this->saleB->id,
            'payment_status' => 'UNPAID',
        ]);
    }

    public function test_tenant_a_cannot_cash_pay_tenant_b_sale(): void
    {
        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson("/api/v1/sales/{$this->saleB->id}/payments/cash", ['paid_amount' => 10000])
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_tenant_a_cannot_checkout_tenant_b_product(): void
    {
        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson('/api/v1/sales', [
                'items' => [['product_id' => $this->productB->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('sales', 1); // only saleB
    }

    public function test_tenant_a_cannot_checkout_using_tenant_b_store(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SKU-A']);

        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson('/api/v1/sales', [
                'store_id' => $this->storeB->id,
                'items' => [['product_id' => $productA->id, 'qty' => 1]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 10000],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_tenant_a_list_excludes_tenant_b_sales(): void
    {
        $list = $this->actingAs($this->cashierA, 'sanctum')
            ->getJson('/api/v1/sales')
            ->assertOk();

        $this->assertCount(0, $list->json('data'));
    }
}
