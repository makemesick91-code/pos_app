<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingInvoice;
use App\Models\SaasBillingPaymentEvidence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing manual payment evidence lifecycle.
 *
 * Manual evidence ONLY: submit, set under review, accept (applies the amount to
 * the invoice through BillingInvoiceService::recalculatePaidState), reject, and
 * void. MANUAL_QRIS_REFERENCE is a label — never a QRIS/payment gateway API call.
 * A REJECTED evidence never updates paid_amount. Overpayment is capped safely. No
 * payment gateway payloads or secrets stored.
 */
class BillingPaymentEvidenceService
{
    use SanitizesBillingCollectionText;

    public function __construct(
        private readonly BillingInvoiceService $invoices,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function submit(SaasBillingInvoice $invoice, array $data, ?User $actor = null): SaasBillingPaymentEvidence
    {
        if (! $invoice->canReceivePaymentEvidence()) {
            throw new InvalidArgumentException("Invoice in status {$invoice->status} cannot receive payment evidence.");
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment evidence amount must be greater than zero.');
        }

        return $invoice->paymentEvidences()->create([
            'payment_reference' => (string) ($data['payment_reference'] ?? $this->generateReference()),
            'status' => SaasBillingPaymentEvidence::STATUS_SUBMITTED,
            'payment_method' => $this->normalizeMethod((string) ($data['payment_method'] ?? SaasBillingPaymentEvidence::METHOD_BANK_TRANSFER)),
            'amount' => $amount,
            'paid_at' => isset($data['paid_at']) ? Carbon::parse((string) $data['paid_at']) : null,
            'received_by_user_id' => $actor?->id ?? ($data['received_by_user_id'] ?? null),
            'evidence_label' => $this->sanitizeNullableString($data['evidence_label'] ?? null),
            'evidence_reference' => $this->sanitizeNullableString($data['evidence_reference'] ?? null),
            'notes' => $this->sanitizeNullableString($data['notes'] ?? null),
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);
    }

    public function underReview(SaasBillingPaymentEvidence $evidence, ?User $actor = null): SaasBillingPaymentEvidence
    {
        if (! in_array($evidence->status, [SaasBillingPaymentEvidence::STATUS_SUBMITTED, SaasBillingPaymentEvidence::STATUS_UNDER_REVIEW], true)) {
            throw new InvalidArgumentException("Cannot move evidence from {$evidence->status} to UNDER_REVIEW.");
        }

        $evidence->status = SaasBillingPaymentEvidence::STATUS_UNDER_REVIEW;
        $evidence->reviewed_by_user_id = $actor?->id;
        $evidence->save();

        return $evidence->refresh();
    }

    public function accept(SaasBillingPaymentEvidence $evidence, ?User $actor = null): SaasBillingPaymentEvidence
    {
        if (! in_array($evidence->status, [SaasBillingPaymentEvidence::STATUS_SUBMITTED, SaasBillingPaymentEvidence::STATUS_UNDER_REVIEW], true)) {
            throw new InvalidArgumentException("Only a SUBMITTED/UNDER_REVIEW evidence can be accepted; got {$evidence->status}.");
        }

        $evidence->status = SaasBillingPaymentEvidence::STATUS_ACCEPTED;
        $evidence->reviewed_by_user_id = $actor?->id ?? $evidence->reviewed_by_user_id;
        $evidence->reviewed_at = Carbon::now();
        $evidence->rejected_reason = null;
        $evidence->save();

        $this->invoices->recalculatePaidState($evidence->invoice);

        return $evidence->refresh();
    }

    public function reject(SaasBillingPaymentEvidence $evidence, string $reason, ?User $actor = null): SaasBillingPaymentEvidence
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $wasAccepted = $evidence->status === SaasBillingPaymentEvidence::STATUS_ACCEPTED;

        $evidence->status = SaasBillingPaymentEvidence::STATUS_REJECTED;
        $evidence->reviewed_by_user_id = $actor?->id ?? $evidence->reviewed_by_user_id;
        $evidence->reviewed_at = Carbon::now();
        $evidence->rejected_reason = $this->sanitizeString($reason);
        $evidence->save();

        // Rejecting a previously accepted evidence rolls back its applied amount.
        if ($wasAccepted) {
            $this->invoices->recalculatePaidState($evidence->invoice);
        }

        return $evidence->refresh();
    }

    public function void(SaasBillingPaymentEvidence $evidence, ?User $actor = null): SaasBillingPaymentEvidence
    {
        $wasAccepted = $evidence->status === SaasBillingPaymentEvidence::STATUS_ACCEPTED;

        $evidence->status = SaasBillingPaymentEvidence::STATUS_VOIDED;
        $evidence->save();

        if ($wasAccepted) {
            $this->invoices->recalculatePaidState($evidence->invoice);
        }

        return $evidence->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasBillingPaymentEvidence::query()->get();

        $byStatus = [];
        foreach (SaasBillingPaymentEvidence::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => 'GO',
            'total_evidences' => $all->count(),
            'by_status' => $byStatus,
            'accepted_amount' => round((float) $all->where('status', SaasBillingPaymentEvidence::STATUS_ACCEPTED)->sum('amount'), 2),
            'no_payment_gateway_call' => true,
        ];
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtoupper(trim($method));
        if (! in_array($method, SaasBillingPaymentEvidence::METHODS, true)) {
            throw new InvalidArgumentException("Invalid payment method: {$method}");
        }

        return $method;
    }

    private function generateReference(): string
    {
        return 'PAYEV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
