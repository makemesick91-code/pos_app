<?php

namespace App\Services\Billing;

use App\Models\TenantBillingInvoice;
use Carbon\CarbonImmutable;

/**
 * Sprint 30 — the controlled invoice status/collection lifecycle (BIL-R004).
 *
 * Every status/collection-state transition goes through here; controllers and
 * commands never assign statuses directly. Illegal transitions throw a governance
 * error. Paid invoices cannot be void/cancelled without an explicit governed
 * reversal (out of scope for the foundation → refused).
 */
class TenantInvoiceStatusService
{
    /**
     * Allowed document-status transitions. `void`/`cancelled` are terminal.
     *
     * @var array<string, list<string>>
     */
    private const STATUS_TRANSITIONS = [
        TenantBillingInvoice::STATUS_DRAFT => [TenantBillingInvoice::STATUS_ISSUED, TenantBillingInvoice::STATUS_CANCELLED],
        TenantBillingInvoice::STATUS_ISSUED => [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED],
        TenantBillingInvoice::STATUS_VOID => [],
        TenantBillingInvoice::STATUS_CANCELLED => [],
    ];

    public function issue(TenantBillingInvoice $invoice): TenantBillingInvoice
    {
        $this->assertTransition($invoice->status, TenantBillingInvoice::STATUS_ISSUED);

        $invoice->status = TenantBillingInvoice::STATUS_ISSUED;
        $invoice->issued_at = $invoice->issued_at ?? CarbonImmutable::now();
        $invoice->save();

        return $invoice;
    }

    public function void(TenantBillingInvoice $invoice): TenantBillingInvoice
    {
        $this->assertNotSettled($invoice, 'void');
        $this->assertTransition($invoice->status, TenantBillingInvoice::STATUS_VOID);

        $invoice->status = TenantBillingInvoice::STATUS_VOID;
        $invoice->collection_state = TenantBillingInvoice::COLLECTION_CANCELLED;
        $invoice->save();

        return $invoice;
    }

    public function cancel(TenantBillingInvoice $invoice): TenantBillingInvoice
    {
        $this->assertNotSettled($invoice, 'cancel');
        $this->assertTransition($invoice->status, TenantBillingInvoice::STATUS_CANCELLED);

        $invoice->status = TenantBillingInvoice::STATUS_CANCELLED;
        $invoice->collection_state = TenantBillingInvoice::COLLECTION_CANCELLED;
        $invoice->save();

        return $invoice;
    }

    /**
     * Recompute the payment collection axis from the confirmed/recorded payments.
     * Never lifts a document status; only moves the collection_state and never
     * marks paid unless the collected amount actually covers the total.
     */
    public function refreshCollectionState(TenantBillingInvoice $invoice, ?CarbonImmutable $now = null): TenantBillingInvoice
    {
        if (in_array($invoice->status, [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED], true)) {
            return $invoice;
        }

        $now ??= CarbonImmutable::now();
        $collected = $invoice->collectedAmount();

        if ($invoice->total_amount > 0 && $collected >= $invoice->total_amount) {
            $invoice->collection_state = TenantBillingInvoice::COLLECTION_PAID;
        } elseif ($invoice->total_amount === 0) {
            $invoice->collection_state = TenantBillingInvoice::COLLECTION_PAID;
        } elseif ($invoice->due_at !== null && $now->greaterThan($invoice->due_at)) {
            $invoice->collection_state = TenantBillingInvoice::COLLECTION_OVERDUE;
        } else {
            $invoice->collection_state = TenantBillingInvoice::COLLECTION_PENDING;
        }

        $invoice->save();

        return $invoice;
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = self::STATUS_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new BillingGovernanceException(
                'BILLING_INVALID_STATUS_TRANSITION',
                "Invalid invoice status transition '{$from}' → '{$to}'.",
            );
        }
    }

    private function assertNotSettled(TenantBillingInvoice $invoice, string $action): void
    {
        if ($invoice->collectedAmount() > 0 || $invoice->isPaid()) {
            throw new BillingGovernanceException(
                'BILLING_INVOICE_SETTLED',
                "Cannot {$action} an invoice with confirmed payments without a governed reversal.",
            );
        }
    }
}
