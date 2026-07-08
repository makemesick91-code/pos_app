<?php

namespace App\Services\PaymentGateway;

use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\BillingAuditService;
use App\Services\PaymentGateway\Data\NormalizedWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 31 — verified, idempotent gateway webhook ingestion (PGW-R007/R008/R009).
 *
 * Every event is: (1) signature-verified — an unsigned/invalid event is stored
 * `rejected` and never processed (PGW-R007); (2) replay-checked — a duplicate
 * provider_event_id / payload is returned as `replayed` and never reprocessed
 * (PGW-R008); (3) normalized to a canonical status; and (4) routed. ONLY a
 * verified `paid` event settles (via PaymentGatewaySettlementService → Sprint 30
 * collection). A failed/expired/cancelled event updates the intent state but
 * NEVER marks the invoice paid (PGW-R009). No raw signature/secret is ever
 * stored (PGW-R011).
 */
class PaymentGatewayWebhookService
{
    public function __construct(
        private readonly PaymentGatewayProviderManager $providers,
        private readonly PaymentGatewaySettlementService $settlement,
        private readonly PaymentGatewayRedactor $redactor,
        private readonly BillingAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function ingest(
        string $providerKey,
        array $payload,
        array $headers = [],
        ?User $actor = null,
        ?Request $request = null,
    ): TenantBillingGatewayEvent {
        $gateway = $this->providers->resolve($providerKey); // throws on unknown/disabled

        $payloadHash = $this->redactor->payloadHash($payload);
        $normalized = $gateway->normalizeWebhook($payload);
        $providerEventId = $normalized->providerEventId;

        // Replay detection BEFORE any processing (PGW-R008).
        $replay = $this->findReplay($providerKey, $providerEventId, $payloadHash);
        if ($replay instanceof TenantBillingGatewayEvent) {
            return $replay;
        }

        $verification = $gateway->verifyWebhookSignature($payload, $headers);

        if (! $verification->verified) {
            return $this->storeRejected($providerKey, $normalized, $payloadHash, $verification->signatureHash, $verification->reason ?? 'invalid_signature');
        }

        return DB::transaction(function () use ($providerKey, $payload, $normalized, $payloadHash, $verification, $actor, $request) {
            $intent = $this->resolveIntent($providerKey, $normalized);

            $event = new TenantBillingGatewayEvent([
                'provider' => $providerKey,
                'event_type' => $normalized->eventType,
                'provider_event_id' => $normalized->providerEventId,
                'provider_reference' => $normalized->providerReference,
                'payment_intent_id' => $intent?->id,
                'invoice_id' => $intent?->invoice_id,
                'payload_hash' => $payloadHash,
                'signature_hash' => $verification->signatureHash,
                'signature_verified' => true,
                'status' => TenantBillingGatewayEvent::STATUS_VERIFIED,
                'normalized_status' => $normalized->normalizedStatus,
                'amount' => $normalized->amount,
                'currency' => $normalized->currency,
                'occurred_at' => $normalized->occurredAt ? CarbonImmutable::parse($normalized->occurredAt) : null,
                'metadata' => $this->redactor->sanitize($normalized->metadata),
            ]);
            $event->save();

            $this->route($event, $intent, $normalized, $actor, $request);

            $this->audit->record(
                actor: $actor,
                action: 'payment-gateway.event.ingested',
                targetType: TenantBillingGatewayEvent::class,
                targetId: $event->id,
                tenantId: $intent?->tenant_id,
                after: [
                    'provider' => $providerKey,
                    'normalized_status' => $event->normalized_status,
                    'status' => $event->status,
                ],
                metadata: ['provider_reference' => $normalized->providerReference],
                request: $request,
            );

            return $event;
        });
    }

    private function route(
        TenantBillingGatewayEvent $event,
        ?TenantBillingPaymentIntent $intent,
        NormalizedWebhookEvent $normalized,
        ?User $actor,
        ?Request $request,
    ): void {
        $settleable = in_array($normalized->normalizedStatus, (array) config('payment_gateway_governance.settleable_statuses', ['paid']), true);

        if ($settleable) {
            if (! $intent instanceof TenantBillingPaymentIntent) {
                $this->finish($event, TenantBillingGatewayEvent::STATUS_IGNORED, 'no_matching_intent');

                return;
            }

            try {
                $this->settlement->settle($intent, $normalized, $actor, $request);
                $this->finish($event, TenantBillingGatewayEvent::STATUS_PROCESSED);
            } catch (PaymentGatewayException $e) {
                // Settlement refused (amount mismatch / expired / duplicate). The
                // invoice is never marked paid; the event records the reason.
                $this->finish($event, TenantBillingGatewayEvent::STATUS_REJECTED, $e->governanceCode);
            }

            return;
        }

        // A non-paid terminal event updates the intent but NEVER settles (PGW-R009).
        $this->applyTerminalToIntent($intent, $normalized->normalizedStatus);

        $status = in_array($normalized->normalizedStatus, ['failed', 'expired', 'cancelled'], true)
            ? TenantBillingGatewayEvent::STATUS_PROCESSED
            : TenantBillingGatewayEvent::STATUS_IGNORED;

        $reason = $normalized->normalizedStatus === 'unknown' ? 'unmapped_status' : null;
        $this->finish($event, $status, $reason);
    }

    private function applyTerminalToIntent(?TenantBillingPaymentIntent $intent, string $normalizedStatus): void
    {
        if (! $intent instanceof TenantBillingPaymentIntent || ! $intent->isOpen()) {
            return; // never override a paid/closed intent
        }

        $intent = TenantBillingPaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
        if (! $intent->isOpen()) {
            return;
        }

        switch ($normalizedStatus) {
            case 'failed':
                $intent->status = TenantBillingPaymentIntent::STATUS_FAILED;
                $intent->failed_at = CarbonImmutable::now();
                break;
            case 'expired':
                $intent->status = TenantBillingPaymentIntent::STATUS_EXPIRED;
                break;
            case 'cancelled':
                $intent->status = TenantBillingPaymentIntent::STATUS_CANCELLED;
                $intent->cancelled_at = CarbonImmutable::now();
                break;
            default:
                return; // pending/requires_action — leave the intent open
        }

        $intent->save();
    }

    private function finish(TenantBillingGatewayEvent $event, string $status, ?string $failureReason = null): void
    {
        $event->status = $status;
        $event->processed_at = CarbonImmutable::now();
        if ($failureReason !== null) {
            $event->failure_reason = $failureReason;
        }
        $event->save();
    }

    private function resolveIntent(string $providerKey, NormalizedWebhookEvent $normalized): ?TenantBillingPaymentIntent
    {
        if ($normalized->providerReference === null || $normalized->providerReference === '') {
            return null;
        }

        return TenantBillingPaymentIntent::query()
            ->where('provider', $providerKey)
            ->where('provider_reference', $normalized->providerReference)
            ->first();
    }

    private function findReplay(string $providerKey, ?string $providerEventId, string $payloadHash): ?TenantBillingGatewayEvent
    {
        $query = TenantBillingGatewayEvent::query()->where('provider', $providerKey);

        if ($providerEventId !== null && $providerEventId !== '') {
            $existing = (clone $query)->where('provider_event_id', $providerEventId)->first();
            if ($existing instanceof TenantBillingGatewayEvent) {
                return $existing;
            }
        }

        return $query->where('payload_hash', $payloadHash)->first();
    }

    private function storeRejected(
        string $providerKey,
        NormalizedWebhookEvent $normalized,
        string $payloadHash,
        ?string $signatureHash,
        string $reason,
    ): TenantBillingGatewayEvent {
        $event = new TenantBillingGatewayEvent([
            'provider' => $providerKey,
            'event_type' => $normalized->eventType,
            'provider_event_id' => $normalized->providerEventId,
            'provider_reference' => $normalized->providerReference,
            'payload_hash' => $payloadHash,
            'signature_hash' => $signatureHash,
            'signature_verified' => false,
            'status' => TenantBillingGatewayEvent::STATUS_REJECTED,
            'normalized_status' => null,
            'processed_at' => CarbonImmutable::now(),
            'failure_reason' => $reason,
        ]);
        $event->save();

        return $event;
    }
}
