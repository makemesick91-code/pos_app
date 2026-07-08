<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\BillingSummaryService;
use App\Services\Billing\TenantInvoiceService;
use App\Services\Billing\TenantPaymentCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 30 — payment collection state summary + outstanding accounting.
 */
class PaymentCollectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'PAY-COLL']);
        $this->admin = User::factory()->platformAdmin()->create();
        $this->assignTenantPlan($this->tenant, 'growth'); // 299000
    }

    public function test_collection_summary_reflects_paid_and_pending(): void
    {
        $invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);

        $summary = app(BillingSummaryService::class)->collectionSummary($this->tenant->id);
        $this->assertSame(1, $summary['total_invoices']);
        $this->assertSame(299000, $summary['total_outstanding_amount']);

        app(TenantPaymentCollectionService::class)->record($invoice, 299000, 'manual', $this->admin, 'full');

        $summary = app(BillingSummaryService::class)->collectionSummary($this->tenant->id);
        $this->assertSame(1, $summary['by_collection_state']['paid']);
        $this->assertSame(299000, $summary['total_collected_amount']);
        $this->assertSame(0, $summary['total_outstanding_amount']);
    }

    public function test_outstanding_never_negative(): void
    {
        $invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        app(TenantPaymentCollectionService::class)->record($invoice, 299000, 'manual', $this->admin, 'full');

        $this->assertSame(0, $invoice->refresh()->outstandingAmount());
    }

    public function test_cannot_pay_a_cancelled_invoice(): void
    {
        $invoice = app(TenantInvoiceService::class)->generate($this->tenant, '2026-07', 'platform_admin', $this->admin);
        app(TenantInvoiceService::class)->cancel($invoice, $this->admin, 'x');

        $this->expectException(BillingGovernanceException::class);
        app(TenantPaymentCollectionService::class)->record($invoice->refresh(), 299000, 'manual', $this->admin);
    }

    public function test_collection_summary_command_runs(): void
    {
        $this->artisan('billing:collection-summary', ['--tenant' => $this->tenant->id])->assertExitCode(0);
    }
}
