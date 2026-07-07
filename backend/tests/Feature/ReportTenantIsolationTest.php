<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 9 — tenant isolation for every report and closing surface. Tenant A can
 * never view, export, create, or show tenant B's reports/closings.
 */
class ReportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Store $storeA;
    private User $userA;

    private Tenant $tenantB;
    private Store $storeB;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);

        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);

        // Tenant B activity that tenant A must never see.
        $saleB = Sale::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'cashier_id' => $this->userB->id,
            'sale_date' => now(),
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
            'grand_total' => 88000,
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'sale_id' => $saleB->id,
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_PAID,
            'amount' => 88000,
        ]);
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);
        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'product_id' => $productB->id,
            'movement_type' => InventoryMovement::TYPE_SALE_OUT,
            'qty' => '9.00',
            'signed_qty' => '-9.00',
        ]);
    }

    public function test_tenant_a_daily_sales_excludes_tenant_b(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 0)
            ->assertJsonPath('data.grand_total', '0.00');
    }

    public function test_tenant_a_payment_summary_excludes_tenant_b(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/reports/payment-summary')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_inventory_summary_excludes_tenant_b(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/reports/inventory-movements-summary')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_csv_export_excludes_tenant_b(): void
    {
        $body = $this->actingAs($this->userA, 'sanctum')
            ->get('/api/v1/reports/daily-sales/export.csv')
            ->streamedContent();

        $this->assertStringNotContainsString('88000', $body);
    }

    public function test_tenant_a_cannot_close_tenant_b_store(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/closings/daily', [
                'store_id' => $this->storeB->id,
                'business_date' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_tenant_a_cannot_show_tenant_b_closing(): void
    {
        $closingB = DailyClosing::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
            'closed_by' => $this->userB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/closings/daily/'.$closingB->id)
            ->assertNotFound();
    }
}
