<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingAccount;
use App\Models\SaasBillingInvoice;
use App\Models\SaasBillingInvoiceLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing invoice lifecycle.
 *
 * Owns the platform-to-tenant invoice: create draft, add/update lines (server-side
 * total calculation), issue, mark overdue/disputed, and void. Totals are ALWAYS
 * recomputed from invoice lines — a client can never force subtotal/total/paid/
 * remaining directly. Issuing NEVER calls a payment gateway and NEVER auto-suspends
 * a tenant. paid_amount/remaining_amount are only ever mutated by the payment
 * evidence review service (recalculatePaidState). No secrets stored.
 */
class BillingInvoiceService
{
    use SanitizesBillingCollectionText;

    /**
     * @param array<string,mixed> $attributes
     */
    public function createDraft(SaasBillingAccount $account, array $attributes = [], ?User $actor = null): SaasBillingInvoice
    {
        return SaasBillingInvoice::query()->create([
            'invoice_reference' => (string) ($attributes['invoice_reference'] ?? $this->generateReference()),
            'invoice_number' => (string) ($attributes['invoice_number'] ?? $this->generateNumber()),
            'billing_account_id' => $account->id,
            'tenant_id' => $attributes['tenant_id'] ?? $account->tenant_id,
            'tenant_subscription_id' => $attributes['tenant_subscription_id'] ?? null,
            'billing_cycle_id' => $attributes['billing_cycle_id'] ?? null,
            'status' => SaasBillingInvoice::STATUS_DRAFT,
            'currency' => strtoupper((string) ($attributes['currency'] ?? $account->billing_currency ?? config('billing_collection.currency', 'IDR'))),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * Lightweight metadata edit for a DRAFT invoice (notes, cycle, subscription
     * link). Money totals are never edited here — they are recomputed from lines.
     *
     * @param array<string,mixed> $data
     */
    public function updateInvoice(SaasBillingInvoice $invoice, array $data, ?User $actor = null): SaasBillingInvoice
    {
        $this->assertEditable($invoice);

        foreach ([
            'tenant_id' => fn ($v) => $v,
            'tenant_subscription_id' => fn ($v) => $v,
            'billing_cycle_id' => fn ($v) => $v,
            'currency' => fn ($v) => strtoupper((string) $v),
            'notes' => fn ($v) => $this->sanitizeNullableString($v),
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ] as $key => $caster) {
            if (array_key_exists($key, $data)) {
                $invoice->{$key} = $caster($data[$key]);
            }
        }

        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addLine(SaasBillingInvoice $invoice, array $data, ?User $actor = null): SaasBillingInvoiceLine
    {
        $this->assertEditable($invoice);

        $quantity = (float) ($data['quantity'] ?? 1);
        $unit = (float) ($data['unit_amount'] ?? 0);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $tax = (float) ($data['tax_amount'] ?? 0);

        $line = $invoice->lines()->create([
            'line_reference' => (string) ($data['line_reference'] ?? $this->generateLineReference()),
            'item_type' => $this->normalizeItemType((string) ($data['item_type'] ?? SaasBillingInvoiceLine::TYPE_OTHER)),
            'description' => $this->sanitizeString((string) ($data['description'] ?? 'Line item')),
            'quantity' => $quantity,
            'unit_amount' => $unit,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'line_total' => $this->lineTotal($quantity, $unit, $discount, $tax),
            'source_type' => $this->sanitizeNullableString($data['source_type'] ?? null),
            'source_id' => $data['source_id'] ?? null,
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);

        $this->recalculateTotals($invoice);

        return $line->refresh();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateLine(SaasBillingInvoice $invoice, SaasBillingInvoiceLine $line, array $data, ?User $actor = null): SaasBillingInvoiceLine
    {
        $this->assertEditable($invoice);

        foreach ([
            'item_type' => fn ($v) => $this->normalizeItemType((string) $v),
            'description' => fn ($v) => $this->sanitizeString((string) $v),
            'quantity' => fn ($v) => (float) $v,
            'unit_amount' => fn ($v) => (float) $v,
            'discount_amount' => fn ($v) => (float) $v,
            'tax_amount' => fn ($v) => (float) $v,
            'source_type' => fn ($v) => $this->sanitizeNullableString($v),
            'source_id' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ] as $key => $caster) {
            if (array_key_exists($key, $data)) {
                $line->{$key} = $caster($data[$key]);
            }
        }

        $line->line_total = $this->lineTotal(
            (float) $line->quantity,
            (float) $line->unit_amount,
            (float) $line->discount_amount,
            (float) $line->tax_amount,
        );
        $line->save();

        $this->recalculateTotals($invoice);

        return $line->refresh();
    }

    /**
     * Recalculate invoice money totals server-side from its lines. Never trusts a
     * client-supplied subtotal/total. Also refreshes remaining_amount.
     */
    public function recalculateTotals(SaasBillingInvoice $invoice): SaasBillingInvoice
    {
        $lines = $invoice->lines()->get();

        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) $line->quantity * (float) $line->unit_amount;
            $discount += (float) $line->discount_amount;
            $tax += (float) $line->tax_amount;
        }

        $total = $subtotal - $discount + $tax;

        $invoice->subtotal_amount = round($subtotal, 2);
        $invoice->discount_amount = round($discount, 2);
        $invoice->tax_amount = round($tax, 2);
        $invoice->total_amount = round($total, 2);
        $invoice->remaining_amount = round(max(0, $total - (float) $invoice->paid_amount), 2);
        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function issue(SaasBillingInvoice $invoice, array $data = [], ?User $actor = null): SaasBillingInvoice
    {
        if ($invoice->status !== SaasBillingInvoice::STATUS_DRAFT) {
            throw new InvalidArgumentException("Only a DRAFT invoice can be issued; got {$invoice->status}.");
        }

        $this->recalculateTotals($invoice);

        $issueDate = isset($data['issue_date']) ? Carbon::parse((string) $data['issue_date']) : Carbon::now();
        $terms = (int) ($invoice->account?->payment_terms_days ?? 7);
        $dueDate = isset($data['due_date'])
            ? Carbon::parse((string) $data['due_date'])
            : (clone $issueDate)->addDays($terms);

        $invoice->status = SaasBillingInvoice::STATUS_ISSUED;
        $invoice->issue_date = $issueDate->toDateString();
        $invoice->due_date = $dueDate->toDateString();
        $invoice->issued_by_user_id = $actor?->id ?? ($data['issued_by_user_id'] ?? null);
        $invoice->issued_at = Carbon::now();
        $invoice->remaining_amount = round(max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount), 2);
        $invoice->save();

        return $invoice->refresh();
    }

    public function markOverdue(SaasBillingInvoice $invoice, ?User $actor = null): SaasBillingInvoice
    {
        if (! in_array($invoice->status, [SaasBillingInvoice::STATUS_ISSUED, SaasBillingInvoice::STATUS_PARTIAL], true)) {
            throw new InvalidArgumentException("Only an ISSUED/PARTIAL invoice can be marked overdue; got {$invoice->status}.");
        }

        $invoice->status = SaasBillingInvoice::STATUS_OVERDUE;
        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function markDisputed(SaasBillingInvoice $invoice, array $data = [], ?User $actor = null): SaasBillingInvoice
    {
        if (in_array($invoice->status, [SaasBillingInvoice::STATUS_VOIDED, SaasBillingInvoice::STATUS_PAID, SaasBillingInvoice::STATUS_ARCHIVED], true)) {
            throw new InvalidArgumentException("Cannot dispute an invoice in status {$invoice->status}.");
        }

        $invoice->status = SaasBillingInvoice::STATUS_DISPUTED;
        if (isset($data['notes'])) {
            $invoice->notes = $this->sanitizeNullableString($data['notes']);
        }
        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function void(SaasBillingInvoice $invoice, array $data = [], ?User $actor = null): SaasBillingInvoice
    {
        if (in_array($invoice->status, [SaasBillingInvoice::STATUS_PAID, SaasBillingInvoice::STATUS_VOIDED, SaasBillingInvoice::STATUS_ARCHIVED], true)) {
            throw new InvalidArgumentException("Cannot void an invoice in status {$invoice->status}.");
        }

        $invoice->status = SaasBillingInvoice::STATUS_VOIDED;
        $invoice->voided_by_user_id = $actor?->id ?? ($data['voided_by_user_id'] ?? null);
        $invoice->voided_at = Carbon::now();
        $invoice->void_reason = $this->sanitizeNullableString($data['void_reason'] ?? $data['reason'] ?? null);
        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * Recompute paid/remaining from ACCEPTED payment evidences ONLY. Called by the
     * payment evidence service — this is the sole path that mutates paid_amount.
     */
    public function recalculatePaidState(SaasBillingInvoice $invoice): SaasBillingInvoice
    {
        $total = (float) $invoice->total_amount;

        $accepted = (float) $invoice->paymentEvidences()
            ->where('status', \App\Models\SaasBillingPaymentEvidence::STATUS_ACCEPTED)
            ->sum('amount');

        // Overpayment is capped safely; paid never exceeds the invoice total.
        $paid = min($total, $accepted);
        $invoice->paid_amount = round($paid, 2);
        $invoice->remaining_amount = round(max(0, $total - $paid), 2);

        if (! in_array($invoice->status, [SaasBillingInvoice::STATUS_VOIDED, SaasBillingInvoice::STATUS_DRAFT, SaasBillingInvoice::STATUS_ARCHIVED], true)) {
            if ($total > 0 && $paid >= $total) {
                $invoice->status = SaasBillingInvoice::STATUS_PAID;
            } elseif ($paid > 0) {
                $invoice->status = SaasBillingInvoice::STATUS_PARTIAL;
            } else {
                $invoice->status = SaasBillingInvoice::STATUS_ISSUED;
            }
        }

        $invoice->save();

        return $invoice->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasBillingInvoice::query()->get();

        $byStatus = [];
        foreach (SaasBillingInvoice::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => 'GO',
            'total_invoices' => $all->count(),
            'by_status' => $byStatus,
            'total_amount' => round((float) $all->sum('total_amount'), 2),
            'paid_amount' => round((float) $all->sum('paid_amount'), 2),
            'remaining_amount' => round((float) $all->sum('remaining_amount'), 2),
            'overdue_count' => $all->where('status', SaasBillingInvoice::STATUS_OVERDUE)->count(),
            'disputed_count' => $all->where('status', SaasBillingInvoice::STATUS_DISPUTED)->count(),
        ];
    }

    private function assertEditable(SaasBillingInvoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw new InvalidArgumentException("Only a DRAFT invoice can be edited; got {$invoice->status}.");
        }
    }

    private function lineTotal(float $quantity, float $unit, float $discount, float $tax): float
    {
        return round(($quantity * $unit) - $discount + $tax, 2);
    }

    private function normalizeItemType(string $type): string
    {
        $type = strtoupper(trim($type));

        return in_array($type, SaasBillingInvoiceLine::ITEM_TYPES, true) ? $type : SaasBillingInvoiceLine::TYPE_OTHER;
    }

    private function generateReference(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function generateNumber(): string
    {
        return 'INVNO-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }

    private function generateLineReference(): string
    {
        return 'INVL-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
