<?php

namespace Tests\Feature;

use App\Models\SaasBillingInvoice;
use App\Services\BillingCollection\BillingAccountService;
use App\Services\BillingCollection\BillingInvoiceService;
use App\Services\BillingCollection\BillingPaymentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPaymentEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function issuedInvoice(float $amount): SaasBillingInvoice
    {
        $account = app(BillingAccountService::class)->create(['billing_name' => 'Acct']);
        $invoices = app(BillingInvoiceService::class);
        $invoice = $invoices->createDraft($account);
        $invoices->addLine($invoice, ['item_type' => 'OTHER', 'description' => 'x', 'quantity' => 1, 'unit_amount' => $amount]);

        return $invoices->issue($invoice->fresh());
    }

    private function service(): BillingPaymentEvidenceService
    {
        return app(BillingPaymentEvidenceService::class);
    }

    public function test_accept_full_payment_marks_invoice_paid(): void
    {
        $invoice = $this->issuedInvoice(100000);
        $evidence = $this->service()->submit($invoice, ['payment_method' => 'BANK_TRANSFER', 'amount' => 100000]);
        $this->service()->accept($evidence);

        $invoice->refresh();
        $this->assertSame(SaasBillingInvoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(100000, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->remaining_amount);
    }

    public function test_accept_partial_payment_marks_invoice_partial(): void
    {
        $invoice = $this->issuedInvoice(100000);
        $evidence = $this->service()->submit($invoice, ['payment_method' => 'CASH_DEPOSIT', 'amount' => 40000]);
        $this->service()->accept($evidence);

        $invoice->refresh();
        $this->assertSame(SaasBillingInvoice::STATUS_PARTIAL, $invoice->status);
        $this->assertEquals(40000, (float) $invoice->paid_amount);
        $this->assertEquals(60000, (float) $invoice->remaining_amount);
    }

    public function test_rejected_evidence_does_not_update_paid_amount(): void
    {
        $invoice = $this->issuedInvoice(100000);
        $evidence = $this->service()->submit($invoice, ['payment_method' => 'BANK_TRANSFER', 'amount' => 100000]);
        $this->service()->reject($evidence, 'invalid proof');

        $invoice->refresh();
        $this->assertEquals(0, (float) $invoice->paid_amount);
        $this->assertSame(SaasBillingInvoice::STATUS_ISSUED, $invoice->status);
    }

    public function test_overpayment_is_capped_safely(): void
    {
        $invoice = $this->issuedInvoice(100000);
        $evidence = $this->service()->submit($invoice, ['payment_method' => 'BANK_TRANSFER', 'amount' => 150000]);
        $this->service()->accept($evidence);

        $invoice->refresh();
        $this->assertSame(SaasBillingInvoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(100000, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->remaining_amount);
    }

    public function test_manual_qris_reference_is_label_only(): void
    {
        $invoice = $this->issuedInvoice(50000);
        $evidence = $this->service()->submit($invoice, [
            'payment_method' => 'MANUAL_QRIS_REFERENCE',
            'amount' => 50000,
            'evidence_label' => 'QRIS ref 12345',
        ]);

        $this->assertSame('MANUAL_QRIS_REFERENCE', $evidence->payment_method);
        // No payment gateway call is made — the evidence is a plain record.
        $this->assertTrue($this->service()->summary()['no_payment_gateway_call']);
    }

    public function test_evidence_notes_are_redacted(): void
    {
        $invoice = $this->issuedInvoice(1000);
        $evidence = $this->service()->submit($invoice, [
            'payment_method' => 'OTHER_MANUAL', 'amount' => 1000,
            'notes' => 'server_key: sk_live_zzz',
        ]);

        $this->assertStringNotContainsString('sk_live_zzz', (string) $evidence->notes);
    }
}
