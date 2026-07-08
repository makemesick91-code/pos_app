<?php

namespace App\Services\PaymentGateway;

use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\BillingAuditService;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\TenantPaymentCollectionService;
use App\Services\PaymentGateway\Data\NormalizedWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 31 — reconcile a VERIFIED PAID provider event to the Sprint 30 payment
 * collection layer (PGW-R010).
 *
 * Settlement NEVER mutates the invoice directly: it calls
 * TenantPaymentCollectionService::record(), which enforces the Sprint 30
 * outstanding/overpayment/partial policy and is itself idempotent — so a replayed
 * paid event never double-collects (PGW-R008/R012). The settlement amount must
 * match the intent amount exactly unless partial payment is explicitly enabled
 * (PGW-R005/R006). Settlement NEVER touches tenant lifecycle, so a manually
 * suspended tenant stays suspended (PGW-R013). An expired intent is refused, not
 * silently paid (PGW-R009).
 */
class PaymentGatewaySettlementService
{
    public function __construct(
        private readonly TenantPaymentCollectionService $collection,
        private readonly PaymentGatewayRedactor $redactor,
        private readonly BillingAuditService $audit,
    ) {}

    public function settle(
        TenantBillingPaymentIntent $intent,
        NormalizedWebhookEvent $event,
        ?User $actor = null,
        ?Request $request = null,
    ): TenantBillingPayment {
        return DB::transaction(function () use ($intent, $event, $actor, $request) {
            $intent = TenantBillingPaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            $invoice = TenantBillingInvoice::query()->lockForUpdate()->findOrFail($intent->invoice_id);

            // Idempotent settlement — a replayed paid event never collects twice.
            if ($intent->isPaid()) {
                $existing = $this->existingSettlementPayment($intent);
                if ($existing instanceof TenantBillingPayment) {
                    return $existing;
                }
            }

            if ($intent->status === TenantBillingPaymentIntent::STATUS_EXPIRED
                || ($intent->expires_at !== null && CarbonImmutable::now()->greaterThan($intent->expires_at) && ! $intent->isPaid())) {
                throw new PaymentGatewayException('GATEWAY_INTENT_EXPIRED', 'Cannot settle an expired payment intent.');
            }

            $amount = $event->amount ?? $intent->amount;
            $this->assertAmount($amount, (int) $intent->amount);

            try {
                $payment = $this->collection->record(
                    invoice: $invoice,
                    amount: (int) $intent->amount,
                    method: 'qris',
                    actor: $actor,
                    reason: 'Gateway settlement '.$intent->provider.'/'.$intent->channel,
                    idempotencyKey: (string) $intent->provider_reference,
                    source: 'gateway',
                    metadata: $this->redactor->sanitize([
                        'gateway_provider' => $intent->provider,
                        'gateway_channel' => $intent->channel,
                        'provider_reference' => $intent->provider_reference,
                        'intent_id' => $intent->id,
                    ]),
                    request: $request,
                );
            } catch (BillingGovernanceException $e) {
                // The Sprint 30 layer refused (e.g. partial/overpayment) — surface as
                // a gateway governance error; the invoice is never marked paid.
                throw new PaymentGatewayException('GATEWAY_SETTLEMENT_REFUSED', $e->getMessage());
            }

            $intent->status = TenantBillingPaymentIntent::STATUS_PAID;
            $intent->paid_at = CarbonImmutable::now();
            $intent->save();

            $this->audit->record(
                actor: $actor,
                action: 'payment-gateway.settlement.recorded',
                targetType: TenantBillingPaymentIntent::class,
                targetId: $intent->id,
                tenantId: $intent->tenant_id,
                after: [
                    'provider' => $intent->provider,
                    'provider_reference' => $intent->provider_reference,
                    'amount' => $intent->amount,
                    'invoice_collection_state' => $invoice->refresh()->collection_state,
                ],
                metadata: ['invoice_number' => $invoice->invoice_number, 'payment_reference' => $payment->payment_reference],
                request: $request,
            );

            return $payment;
        });
    }

    private function existingSettlementPayment(TenantBillingPaymentIntent $intent): ?TenantBillingPayment
    {
        $key = hash('sha256', 'payment:'.$intent->invoice_id.':'.$intent->provider_reference);

        return TenantBillingPayment::query()->where('idempotency_key', $key)->first();
    }

    private function assertAmount(int $eventAmount, int $intentAmount): void
    {
        if (! (bool) config('payment_gateway_governance.allow_overpayment', false) && $eventAmount > $intentAmount) {
            throw new PaymentGatewayException('GATEWAY_OVERPAYMENT', "Settlement amount ({$eventAmount}) exceeds the intent amount ({$intentAmount}).");
        }

        if (! (bool) config('payment_gateway_governance.allow_partial_payment', false) && $eventAmount < $intentAmount) {
            throw new PaymentGatewayException('GATEWAY_AMOUNT_MISMATCH', "Settlement amount ({$eventAmount}) does not match the intent amount ({$intentAmount}).");
        }
    }
}
