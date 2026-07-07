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
 * Sprint 5 — payments:reconcile foundation. Expired PENDING QRIS payments become
 * EXPIRED (and reconcile their sale); non-expired ones and CASH payments are
 * untouched. No external gateway call is involved.
 */
class PaymentReconciliationCommandTest extends TestCase
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

    private function sale(string $status = Sale::PAYMENT_STATUS_PENDING): Sale
    {
        return Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'grand_total' => 20000,
            'payment_status' => $status,
        ]);
    }

    private function qrisPayment(Sale $sale, string $status, ?string $expiredAt): Payment
    {
        return Payment::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_QRIS,
            'amount' => '20000.00',
            'status' => $status,
            'provider' => Payment::PROVIDER_FAKE,
            'provider_reference' => 'FAKE-QRIS-'.$sale->id,
            'expired_at' => $expiredAt,
        ]);
    }

    public function test_command_is_registered(): void
    {
        $this->artisan('payments:reconcile', ['--date' => now()->toDateString()])
            ->assertExitCode(0);
    }

    public function test_expired_pending_qris_becomes_expired(): void
    {
        $sale = $this->sale();
        $payment = $this->qrisPayment($sale, Payment::STATUS_PENDING, now()->subMinutes(30)->toDateTimeString());

        $this->artisan('payments:reconcile', ['--date' => now()->toDateString()])
            ->expectsOutputToContain('Expired locally: 1')
            ->assertExitCode(0);

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(Sale::PAYMENT_STATUS_EXPIRED, $sale->fresh()->payment_status);
    }

    public function test_non_expired_pending_qris_remains_pending(): void
    {
        $sale = $this->sale();
        $payment = $this->qrisPayment($sale, Payment::STATUS_PENDING, now()->addMinutes(30)->toDateTimeString());

        $this->artisan('payments:reconcile', ['--date' => now()->toDateString()])
            ->assertExitCode(0);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PENDING, $sale->fresh()->payment_status);
    }

    public function test_command_does_not_touch_cash_payments(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID);
        $cash = Payment::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_CASH,
            'amount' => '20000.00',
            'status' => Payment::STATUS_PAID,
            'provider' => Payment::PROVIDER_MANUAL,
            'paid_at' => now(),
        ]);

        $this->artisan('payments:reconcile', ['--date' => now()->toDateString()])
            ->assertExitCode(0);

        $this->assertSame(Payment::STATUS_PAID, $cash->fresh()->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $sale->fresh()->payment_status);
    }
}
