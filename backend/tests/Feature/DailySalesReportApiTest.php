<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 9 — the daily sales summary is backend-authoritative. Only PAID sales
 * count as revenue; cancelled sales are counted separately; pending QRIS is
 * never counted as paid revenue. Offline cash sales count only once they exist
 * in the backend (i.e. after sync).
 */
class DailySalesReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);
    }

    private function paidSale(string $method, array $overrides = [], ?Store $store = null): Sale
    {
        $store ??= $this->store;

        $sale = Sale::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
            'subtotal' => 10000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10000,
            'paid_total' => 15000,
            'change_total' => 5000,
        ], $overrides));

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'sale_id' => $sale->id,
            'method' => $method,
            'status' => Payment::STATUS_PAID,
            'amount' => $sale->grand_total,
        ]);

        return $sale;
    }

    public function test_paid_cash_and_qris_sales_count_as_revenue(): void
    {
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000]);
        $this->paidSale(Payment::METHOD_QRIS, ['grand_total' => 20000, 'change_total' => 0, 'paid_total' => 20000]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.grand_total', '30000.00')
            ->assertJsonPath('data.cash_sales_count', 1)
            ->assertJsonPath('data.qris_sales_count', 1);
    }

    public function test_pending_qris_is_not_counted_as_paid_revenue(): void
    {
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000]);

        // A pending QRIS sale: neither the sale nor the payment is PAID.
        $pending = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'payment_status' => Sale::PAYMENT_STATUS_PENDING,
            'grand_total' => 50000,
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'sale_id' => $pending->id,
            'method' => Payment::METHOD_QRIS,
            'status' => Payment::STATUS_PENDING,
            'amount' => 50000,
            'paid_at' => null,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.grand_total', '10000.00')
            ->assertJsonPath('data.qris_sales_count', 0);
    }

    public function test_cancelled_sales_are_counted_separately_not_as_revenue(): void
    {
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000]);

        Sale::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'grand_total' => 99000,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.cancelled_sales_count', 1)
            ->assertJsonPath('data.grand_total', '10000.00');
    }

    public function test_offline_cash_sale_counts_after_synced_to_backend(): void
    {
        // An offline CASH sale becomes a backend PAID row once synced.
        $this->paidSale(Payment::METHOD_CASH, [
            'grand_total' => 12000,
            'source' => Sale::SOURCE_ANDROID_OFFLINE,
            'sync_status' => Sale::SYNC_STATUS_SYNCED,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.grand_total', '12000.00')
            ->assertJsonPath('data.cash_sales_count', 1);
    }

    public function test_store_filter_scopes_the_report(): void
    {
        $otherStore = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A2']);
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000]);
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 77000], $otherStore);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.grand_total', '10000.00');
    }

    public function test_date_filter_excludes_other_days(): void
    {
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000, 'sale_date' => now()->subDays(3)]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.sales_count', 0)
            ->assertJsonPath('data.grand_total', '0.00');
    }

    public function test_cashier_filter_scopes_the_report(): void
    {
        $otherCashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_CASHIER,
        ]);
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 10000]);
        $this->paidSale(Payment::METHOD_CASH, ['grand_total' => 33000, 'cashier_id' => $otherCashier->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales?cashier_id='.$this->user->id)
            ->assertOk()
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.grand_total', '10000.00');
    }
}
