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
 * Sprint 9 — payment summary groups by (method, status). Only PAID rows are
 * realized revenue; pending/failed/expired rows are reported separately and
 * never folded into a paid total.
 */
class PaymentSummaryReportApiTest extends TestCase
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

    private function payment(string $method, string $status, float $amount): void
    {
        $sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'payment_status' => $status === Payment::STATUS_PAID
                ? Sale::PAYMENT_STATUS_PAID
                : Sale::PAYMENT_STATUS_PENDING,
            'grand_total' => $amount,
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'sale_id' => $sale->id,
            'method' => $method,
            'status' => $status,
            'amount' => $amount,
            'paid_at' => $status === Payment::STATUS_PAID ? now() : null,
        ]);
    }

    public function test_cash_and_qris_paid_totals_are_correct(): void
    {
        $this->payment(Payment::METHOD_CASH, Payment::STATUS_PAID, 70000);
        $this->payment(Payment::METHOD_QRIS, Payment::STATUS_PAID, 30000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/payment-summary')
            ->assertOk();

        $rows = collect($response->json('data'));

        $cash = $rows->firstWhere('method', Payment::METHOD_CASH);
        $qris = $rows->firstWhere('method', Payment::METHOD_QRIS);

        $this->assertSame('PAID', $cash['status']);
        $this->assertSame(1, $cash['count']);
        $this->assertSame('70000.00', $cash['amount_total']);
        $this->assertSame('30000.00', $qris['amount_total']);
    }

    public function test_pending_and_failed_are_not_mixed_into_paid_total(): void
    {
        $this->payment(Payment::METHOD_CASH, Payment::STATUS_PAID, 50000);
        $this->payment(Payment::METHOD_QRIS, Payment::STATUS_PENDING, 40000);
        $this->payment(Payment::METHOD_QRIS, Payment::STATUS_FAILED, 25000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/payment-summary')
            ->assertOk();

        $rows = collect($response->json('data'));

        $paidCash = $rows->first(fn ($r) => $r['method'] === 'CASH' && $r['status'] === 'PAID');
        $this->assertSame('50000.00', $paidCash['amount_total']);

        // Pending/failed QRIS surface as their own rows, never as PAID.
        $paidQris = $rows->first(fn ($r) => $r['method'] === 'QRIS' && $r['status'] === 'PAID');
        $this->assertNull($paidQris);

        $pendingQris = $rows->first(fn ($r) => $r['method'] === 'QRIS' && $r['status'] === 'PENDING');
        $this->assertSame('40000.00', $pendingQris['amount_total']);
    }

    public function test_summary_is_store_scoped(): void
    {
        $otherStore = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A2']);
        $this->payment(Payment::METHOD_CASH, Payment::STATUS_PAID, 12000);

        $otherSale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $otherStore->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
            'grand_total' => 88000,
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $otherStore->id,
            'sale_id' => $otherSale->id,
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_PAID,
            'amount' => 88000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/payment-summary?store_id='.$this->store->id)
            ->assertOk();

        $rows = collect($response->json('data'));
        $cash = $rows->firstWhere('method', Payment::METHOD_CASH);
        $this->assertSame('12000.00', $cash['amount_total']);
    }
}
