<?php

namespace App\Services\Billing;

use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sprint 30 — payment collection state foundation (BIL-R009/R010).
 *
 * A payment is a recorded fact, not a gateway charge. Amounts may never be
 * negative and (unless explicitly allowed) never exceed the invoice outstanding
 * amount, so collected revenue is never overstated. Recording is idempotent. A
 * failed/cancelled payment never marks the invoice paid — the invoice collection
 * state is always recomputed from the payments that still count.
 */
class TenantPaymentCollectionService
{
    public function __construct(
        private readonly TenantInvoiceStatusService $status,
        private readonly BillingMetadataSanitizer $sanitizer,
        private readonly BillingAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        TenantBillingInvoice $invoice,
        int $amount,
        string $method = 'manual',
        ?User $actor = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        string $source = 'platform_admin',
        ?array $metadata = null,
        ?Request $request = null,
    ): TenantBillingPayment {
        if (in_array($invoice->status, [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED], true)) {
            throw new BillingGovernanceException(
                'BILLING_INVOICE_NOT_PAYABLE',
                'Cannot record a payment against a void or cancelled invoice.',
            );
        }

        if ($amount <= 0) {
            throw new BillingGovernanceException(
                'BILLING_INVALID_PAYMENT_AMOUNT',
                'Payment amount must be a positive integer.',
            );
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $actor, $reason, $idempotencyKey, $source, $metadata, $request) {
            $invoice = TenantBillingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $key = $idempotencyKey !== null && $idempotencyKey !== ''
                ? hash('sha256', 'payment:'.$invoice->id.':'.$idempotencyKey)
                : hash('sha256', "payment:{$invoice->id}:{$amount}:{$method}:{$source}");

            $existing = TenantBillingPayment::query()->where('idempotency_key', $key)->first();
            if ($existing instanceof TenantBillingPayment) {
                return $existing;
            }

            $outstanding = $invoice->outstandingAmount();

            if (! (bool) config('billing_governance.allow_overpayment', false) && $amount > $outstanding) {
                throw new BillingGovernanceException(
                    'BILLING_OVERPAYMENT',
                    "Payment amount ({$amount}) exceeds invoice outstanding amount ({$outstanding}).",
                );
            }

            if (! (bool) config('billing_governance.allow_partial_payments', false) && $amount < $outstanding) {
                throw new BillingGovernanceException(
                    'BILLING_PARTIAL_NOT_ALLOWED',
                    "Partial payment ({$amount} of {$outstanding}) is not allowed; settle the full outstanding amount.",
                );
            }

            $payment = new TenantBillingPayment([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'payment_reference' => 'PAY-'.strtoupper(Str::random(12)),
                'amount' => $amount,
                'currency' => $invoice->currency,
                'method' => $method,
                'status' => TenantBillingPayment::STATUS_RECORDED,
                'collection_state' => $invoice->collection_state,
                'received_at' => CarbonImmutable::now(),
                'recorded_by_user_id' => $actor?->id,
                'source' => $source,
                'idempotency_key' => $key,
                'reason' => $reason,
                'metadata' => $this->sanitizer->sanitize($metadata),
            ]);
            $payment->save();

            $this->status->refreshCollectionState($invoice->refresh());

            $this->audit->record(
                actor: $actor,
                action: 'billing.payment.recorded',
                targetType: TenantBillingPayment::class,
                targetId: $payment->id,
                tenantId: $invoice->tenant_id,
                after: $this->auditSnapshot($payment),
                metadata: ['reason' => $reason, 'invoice_number' => $invoice->invoice_number],
                request: $request,
            );

            return $payment;
        });
    }

    public function markFailed(TenantBillingPayment $payment, ?User $actor = null, ?string $reason = null, ?Request $request = null): TenantBillingPayment
    {
        return $this->transition($payment, TenantBillingPayment::STATUS_FAILED, 'billing.payment.failed', $actor, $reason, $request);
    }

    public function cancel(TenantBillingPayment $payment, ?User $actor = null, ?string $reason = null, ?Request $request = null): TenantBillingPayment
    {
        return $this->transition($payment, TenantBillingPayment::STATUS_CANCELLED, 'billing.payment.cancelled', $actor, $reason, $request);
    }

    private function transition(TenantBillingPayment $payment, string $to, string $action, ?User $actor, ?string $reason, ?Request $request): TenantBillingPayment
    {
        if (! in_array($payment->status, [TenantBillingPayment::STATUS_PENDING, TenantBillingPayment::STATUS_RECORDED], true)) {
            throw new BillingGovernanceException(
                'BILLING_INVALID_PAYMENT_TRANSITION',
                "Cannot move payment from '{$payment->status}' to '{$to}'.",
            );
        }

        return DB::transaction(function () use ($payment, $to, $action, $actor, $reason, $request) {
            $before = $this->auditSnapshot($payment);

            $payment->status = $to;
            $payment->save();

            $invoice = $payment->invoice()->lockForUpdate()->first();
            if ($invoice instanceof TenantBillingInvoice) {
                $this->status->refreshCollectionState($invoice);
            }

            $this->audit->record(
                actor: $actor,
                action: $action,
                targetType: TenantBillingPayment::class,
                targetId: $payment->id,
                tenantId: $payment->tenant_id,
                before: $before,
                after: $this->auditSnapshot($payment),
                metadata: ['reason' => $reason],
                request: $request,
            );

            return $payment;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(TenantBillingPayment $payment): array
    {
        return [
            'payment_reference' => $payment->payment_reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method,
            'status' => $payment->status,
        ];
    }
}
