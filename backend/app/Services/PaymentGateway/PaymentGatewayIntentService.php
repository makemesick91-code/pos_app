<?php

namespace App\Services\PaymentGateway;

use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\BillingAuditService;
use App\Services\PaymentGateway\Contracts\PaymentGatewayProviderContract;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 31 — create/return an idempotent payment intent for a tenant billing
 * invoice (PGW-R003/R004/R005/R006).
 *
 * The intent amount is ALWAYS the invoice outstanding amount — never client
 * input; partial/overpayment are structurally impossible here. A paid invoice
 * refuses a new payable intent (PGW-R004). While an intent is open it is returned
 * unchanged on retry (PGW-R003). Metadata is redacted (PGW-R011) and every
 * mutation is audit-logged (PGW-R014). This service never mutates the invoice —
 * settlement is the only path to collection, via the Sprint 30 service.
 */
class PaymentGatewayIntentService
{
    public function __construct(
        private readonly PaymentGatewayProviderManager $providers,
        private readonly PaymentGatewayRedactor $redactor,
        private readonly BillingAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function create(
        TenantBillingInvoice $invoice,
        ?string $provider = null,
        ?string $channel = null,
        ?User $actor = null,
        string $source = 'platform_admin',
        ?array $metadata = null,
        ?string $idempotencyKey = null,
        ?Request $request = null,
    ): TenantBillingPaymentIntent {
        $providerKey = $provider ?? (string) config('payment_gateway_governance.default_provider', 'mock');
        $gateway = $this->providers->resolve($providerKey); // throws on unknown/disabled/not-wired

        $channels = $this->providers->channelsFor($providerKey);
        $channel ??= $channels[0] ?? 'mock_qris';
        if (! in_array($channel, $channels, true)) {
            throw new PaymentGatewayException(
                'GATEWAY_CHANNEL_UNSUPPORTED',
                "Channel '{$channel}' is not supported by provider '{$providerKey}'.",
            );
        }

        $this->assertInvoicePayable($invoice);

        return DB::transaction(function () use ($invoice, $gateway, $providerKey, $channel, $actor, $source, $metadata, $idempotencyKey, $request) {
            $invoice = TenantBillingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->assertInvoicePayable($invoice);

            $outstanding = $invoice->outstandingAmount();
            if ($outstanding <= 0) {
                throw new PaymentGatewayException(
                    'GATEWAY_NO_OUTSTANDING_AMOUNT',
                    'Cannot create a payment intent for an invoice with no outstanding amount.',
                );
            }

            // Exact idempotency for a caller-supplied key (e.g. an Idempotency-Key
            // header) — return the prior intent unchanged.
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $key = hash('sha256', "intent:{$invoice->id}:{$providerKey}:{$channel}:{$idempotencyKey}");
                $prior = TenantBillingPaymentIntent::query()->where('idempotency_key', $key)->first();
                if ($prior instanceof TenantBillingPaymentIntent) {
                    return $prior;
                }
            } else {
                // Idempotent while an attempt is open (PGW-R003): reuse the open intent.
                $open = TenantBillingPaymentIntent::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('provider', $providerKey)
                    ->where('channel', $channel)
                    ->whereIn('status', TenantBillingPaymentIntent::OPEN_STATUSES)
                    ->first();
                if ($open instanceof TenantBillingPaymentIntent) {
                    return $open;
                }

                $attempt = TenantBillingPaymentIntent::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('provider', $providerKey)
                    ->where('channel', $channel)
                    ->count() + 1;
                $key = hash('sha256', "intent:{$invoice->id}:{$providerKey}:{$channel}:{$attempt}");
            }

            $ttl = (int) config('payment_gateway_governance.intent_ttl_minutes', 30);

            $intent = new TenantBillingPaymentIntent([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'provider' => $providerKey,
                'channel' => $channel,
                'period_key' => $invoice->period_key,
                'amount' => $outstanding,
                'currency' => $invoice->currency,
                'status' => TenantBillingPaymentIntent::STATUS_PENDING,
                'idempotency_key' => $key,
                'expires_at' => CarbonImmutable::now()->addMinutes($ttl),
                'created_by_user_id' => $actor?->id,
                'source' => $source,
                'metadata' => $this->redactor->sanitize($metadata),
            ]);
            $intent->save();

            $result = $gateway->createPaymentIntent($intent, ['invoice_number' => $invoice->invoice_number]);
            $this->assertReferenceUnique($providerKey, $result->providerReference, $intent->id);

            $intent->provider_reference = $result->providerReference;
            $intent->status = $result->status;
            if ($result->expiresAt !== null) {
                $intent->expires_at = CarbonImmutable::parse($result->expiresAt);
            }
            $intent->metadata = $this->redactor->sanitize(array_merge((array) $intent->metadata, $result->metadata));
            $intent->save();

            $this->audit->record(
                actor: $actor,
                action: 'payment-gateway.intent.created',
                targetType: TenantBillingPaymentIntent::class,
                targetId: $intent->id,
                tenantId: $invoice->tenant_id,
                after: $this->auditSnapshot($intent),
                metadata: ['invoice_number' => $invoice->invoice_number, 'provider' => $providerKey, 'channel' => $channel],
                request: $request,
            );

            return $intent;
        });
    }

    /**
     * Read-only dry-run: what an intent WOULD be, without creating it. Safe for
     * command output; never mutates and never contacts a provider.
     *
     * @return array<string, mixed>
     */
    public function preview(TenantBillingInvoice $invoice, ?string $provider = null, ?string $channel = null): array
    {
        $providerKey = $provider ?? (string) config('payment_gateway_governance.default_provider', 'mock');
        $channels = $this->providers->channelsFor($providerKey);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'provider' => $providerKey,
            'channel' => $channel ?? ($channels[0] ?? 'mock_qris'),
            'amount' => $invoice->outstandingAmount(),
            'currency' => $invoice->currency,
            'payable' => $this->isPayable($invoice),
        ];
    }

    private function assertInvoicePayable(TenantBillingInvoice $invoice): void
    {
        if (in_array($invoice->status, [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED], true)) {
            throw new PaymentGatewayException('GATEWAY_INVOICE_NOT_PAYABLE', 'Cannot create a payment intent for a void or cancelled invoice.');
        }

        if ($invoice->isPaid()) {
            throw new PaymentGatewayException('GATEWAY_INVOICE_ALREADY_PAID', 'Cannot create a payment intent for an already-paid invoice.');
        }
    }

    private function isPayable(TenantBillingInvoice $invoice): bool
    {
        return ! in_array($invoice->status, [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED], true)
            && ! $invoice->isPaid()
            && $invoice->outstandingAmount() > 0;
    }

    private function assertReferenceUnique(string $provider, string $reference, int $currentIntentId): void
    {
        $clash = TenantBillingPaymentIntent::query()
            ->where('provider', $provider)
            ->where('provider_reference', $reference)
            ->where('id', '!=', $currentIntentId)
            ->exists();

        if ($clash) {
            throw new PaymentGatewayException('GATEWAY_DUPLICATE_PROVIDER_REFERENCE', 'Provider reference is already in use for this provider.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(TenantBillingPaymentIntent $intent): array
    {
        return [
            'provider' => $intent->provider,
            'channel' => $intent->channel,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'status' => $intent->status,
            'provider_reference' => $intent->provider_reference,
        ];
    }
}
