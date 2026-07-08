<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\User;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 30 — payment collection is idempotent, never overstates revenue, and a
 * failed/cancelled payment never marks an invoice paid (BIL-R009/R010).
 */
class BillingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private TenantBillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'BILL-PAY']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter'); // 99000
        $this->invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
    }

    private function payments(): TenantPaymentCollectionService
    {
        return app(TenantPaymentCollectionService::class);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'full');

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(BillingGovernanceException::class);
        $this->payments()->record($this->invoice, -5, 'manual', $this->admin);
    }

    public function test_overpayment_is_rejected(): void
    {
        $this->expectException(BillingGovernanceException::class);
        $this->payments()->record($this->invoice, 99001, 'manual', $this->admin);
    }

    public function test_partial_payment_is_rejected_by_default(): void
    {
        $this->expectException(BillingGovernanceException::class);
        $this->payments()->record($this->invoice, 50000, 'manual', $this->admin);
    }

    public function test_payment_is_idempotent(): void
    {
        $a = $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'x', 'idem-1');
        $b = $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'x', 'idem-1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TenantBillingPayment::query()->where('invoice_id', $this->invoice->id)->count());
    }

    public function test_failed_payment_does_not_mark_invoice_paid(): void
    {
        $payment = $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'full');
        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);

        $this->payments()->markFailed($payment, $this->admin, 'bounced');

        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_cancelled_payment_does_not_mark_invoice_paid(): void
    {
        $payment = $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'full');
        $this->payments()->cancel($payment, $this->admin, 'mistake');

        $this->assertNotSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_payment_metadata_is_redacted(): void
    {
        $payment = $this->payments()->record(
            $this->invoice, 99000, 'manual', $this->admin, 'full', null, 'platform_admin',
            ['ref' => 'ok', 'card_number' => '4111111111111111'],
        );

        $this->assertArrayHasKey('ref', $payment->metadata);
        $this->assertArrayNotHasKey('card_number', $payment->metadata);
    }

    public function test_payment_mutation_is_audit_logged(): void
    {
        $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'full');

        $this->assertTrue(AdminAuditLog::query()->where('action', 'billing.payment.recorded')->exists());
    }

    // --- admin API ----------------------------------------------------------

    public function test_platform_admin_can_record_payment_via_api(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/invoices/{$this->invoice->id}/payments", ['amount' => 99000, 'method' => 'manual'])
            ->assertStatus(201);

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $this->invoice->refresh()->collection_state);
    }

    public function test_non_admin_cannot_record_payment(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/invoices/{$this->invoice->id}/payments", ['amount' => 99000])
            ->assertForbidden();
    }

    public function test_mark_failed_via_api_requires_reason(): void
    {
        $payment = $this->payments()->record($this->invoice, 99000, 'manual', $this->admin, 'full');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/payments/{$payment->id}/mark-failed", [])
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenant-billing/payments/{$payment->id}/mark-failed", ['reason' => 'chargeback'])
            ->assertOk();
    }
}
