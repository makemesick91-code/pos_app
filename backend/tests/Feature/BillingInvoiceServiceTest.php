<?php

namespace Tests\Feature;

use App\Models\SaasBillingAccount;
use App\Models\SaasBillingInvoice;
use App\Services\BillingCollection\BillingAccountService;
use App\Services\BillingCollection\BillingInvoiceService;
use App\Services\BillingCollection\BillingPaymentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingInvoiceService
    {
        return app(BillingInvoiceService::class);
    }

    private function account(int $terms = 7): SaasBillingAccount
    {
        return app(BillingAccountService::class)->create([
            'billing_name' => 'Acct',
            'payment_terms_days' => $terms,
        ]);
    }

    public function test_can_create_draft_and_add_lines_with_server_side_totals(): void
    {
        $invoice = $this->service()->createDraft($this->account());
        $this->assertSame(SaasBillingInvoice::STATUS_DRAFT, $invoice->status);

        $this->service()->addLine($invoice, [
            'item_type' => 'SUBSCRIPTION', 'description' => 'Monthly',
            'quantity' => 2, 'unit_amount' => 100000, 'discount_amount' => 10000, 'tax_amount' => 5000,
        ]);

        $invoice->refresh();
        // subtotal = 2*100000 = 200000; discount 10000; tax 5000; total 195000.
        $this->assertEquals(200000, (float) $invoice->subtotal_amount);
        $this->assertEquals(10000, (float) $invoice->discount_amount);
        $this->assertEquals(5000, (float) $invoice->tax_amount);
        $this->assertEquals(195000, (float) $invoice->total_amount);
        $this->assertEquals(195000, (float) $invoice->remaining_amount);
    }

    public function test_client_cannot_force_total_fields_directly(): void
    {
        // The service never reads a client-supplied subtotal/total; it recomputes.
        $invoice = $this->service()->createDraft($this->account(), [
            'subtotal_amount' => 999999, 'total_amount' => 999999, 'paid_amount' => 999999,
        ]);

        $this->assertEquals(0, (float) $invoice->total_amount);
        $this->assertEquals(0, (float) $invoice->paid_amount);
    }

    public function test_can_issue_invoice_with_due_date(): void
    {
        $invoice = $this->service()->createDraft($this->account(10));
        $this->service()->addLine($invoice, ['item_type' => 'SETUP', 'description' => 'x', 'quantity' => 1, 'unit_amount' => 50000]);

        $issued = $this->service()->issue($invoice->fresh(), ['issue_date' => '2026-07-01']);

        $this->assertSame(SaasBillingInvoice::STATUS_ISSUED, $issued->status);
        $this->assertSame('2026-07-11', $issued->due_date->toDateString());
        $this->assertNotNull($issued->issued_at);
    }

    public function test_can_mark_overdue_and_disputed(): void
    {
        $invoice = $this->service()->createDraft($this->account());
        $this->service()->addLine($invoice, ['item_type' => 'OTHER', 'description' => 'x', 'quantity' => 1, 'unit_amount' => 1000]);
        $this->service()->issue($invoice->fresh());

        $this->assertSame(SaasBillingInvoice::STATUS_OVERDUE, $this->service()->markOverdue($invoice->fresh())->status);

        $invoice2 = $this->service()->createDraft($this->account());
        $this->service()->addLine($invoice2, ['item_type' => 'OTHER', 'description' => 'x', 'quantity' => 1, 'unit_amount' => 1000]);
        $this->service()->issue($invoice2->fresh());
        $this->assertSame(SaasBillingInvoice::STATUS_DISPUTED, $this->service()->markDisputed($invoice2->fresh())->status);
    }

    public function test_can_void_invoice_and_voided_cannot_receive_evidence(): void
    {
        $invoice = $this->service()->createDraft($this->account());
        $this->service()->addLine($invoice, ['item_type' => 'OTHER', 'description' => 'x', 'quantity' => 1, 'unit_amount' => 1000]);
        $this->service()->issue($invoice->fresh());

        $voided = $this->service()->void($invoice->fresh(), ['void_reason' => 'error']);
        $this->assertSame(SaasBillingInvoice::STATUS_VOIDED, $voided->status);
        $this->assertFalse($voided->canReceivePaymentEvidence());

        $this->expectException(InvalidArgumentException::class);
        app(BillingPaymentEvidenceService::class)->submit($voided, ['payment_method' => 'BANK_TRANSFER', 'amount' => 1000]);
    }

    public function test_draft_only_editing(): void
    {
        $invoice = $this->service()->createDraft($this->account());
        $this->service()->addLine($invoice, ['item_type' => 'OTHER', 'description' => 'x', 'quantity' => 1, 'unit_amount' => 1000]);
        $this->service()->issue($invoice->fresh());

        // Cannot add a line to a non-DRAFT invoice.
        $this->expectException(InvalidArgumentException::class);
        $this->service()->addLine($invoice->fresh(), ['item_type' => 'OTHER', 'description' => 'y', 'quantity' => 1, 'unit_amount' => 1]);
    }
}
