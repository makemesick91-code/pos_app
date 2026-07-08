<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\User;
use App\Services\Billing\BillingSummaryService;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantInvoiceStatusService;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 30 — subscription renewal/dunning may READ billing state but must never
 * bypass the invoice/payment lifecycle services (BIL-R012), never mark an invoice
 * paid, and never duplicate-generate for the same tenant/period.
 */
class BillingRenewalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'BILL-RENEW']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'starter');
    }

    public function test_renewal_source_does_not_duplicate_invoice_for_same_period(): void
    {
        $adminInvoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        $renewalInvoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'renewal', $this->admin);

        $this->assertSame($adminInvoice->id, $renewalInvoice->id);
        $this->assertSame(1, TenantBillingInvoice::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_dunning_can_read_overdue_unpaid_summary(): void
    {
        $invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'renewal', $this->admin);
        $invoice->due_at = now()->subDays(2);
        $invoice->save();
        app(TenantInvoiceStatusService::class)->refreshCollectionState($invoice->refresh());

        $summary = app(BillingSummaryService::class)->collectionSummary($this->tenant->id);

        $this->assertGreaterThanOrEqual(1, $summary['by_collection_state']['overdue']);
        $this->assertSame(99000, $summary['total_outstanding_amount']);
    }

    public function test_manual_payment_state_is_authoritative_after_renewal_read(): void
    {
        $invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'renewal', $this->admin);
        app(TenantPaymentCollectionService::class)->record($invoice, 99000, 'manual', $this->admin, 'full');

        // A later renewal generation for the same period must not touch the paid state.
        app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'renewal', $this->admin);

        $this->assertSame(TenantBillingInvoice::COLLECTION_PAID, $invoice->refresh()->collection_state);
    }
}
