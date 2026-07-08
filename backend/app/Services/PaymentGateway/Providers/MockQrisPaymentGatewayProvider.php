<?php

namespace App\Services\PaymentGateway\Providers;

use App\Models\TenantBillingPaymentIntent;
use App\Services\PaymentGateway\Contracts\PaymentGatewayProviderContract;
use App\Services\PaymentGateway\Data\NormalizedWebhookEvent;
use App\Services\PaymentGateway\Data\PaymentIntentResult;
use App\Services\PaymentGateway\Data\WebhookVerification;

/**
 * Sprint 31 — deterministic mock QRIS provider (PGW-R002/R018). Performs NO
 * network call and holds NO real credential. A fixed, non-secret signing key
 * makes every operation reproducible for tests and smoke. The provider reference
 * is derived deterministically from the invoice/provider/channel so re-creating
 * an intent yields a stable reference (supports idempotency).
 *
 * The signing key here is NOT a secret — it is a public, deterministic test
 * constant. Real providers use their own credentials read from env at runtime.
 */
class MockQrisPaymentGatewayProvider implements PaymentGatewayProviderContract
{
    /** Public, deterministic test signing key — never a real secret. */
    private const MOCK_SIGNING_KEY = 'sprint31-mock-qris-deterministic-key';

    public const REFERENCE_PREFIX = 'MOCK-QRIS-';

    public function key(): string
    {
        return 'mock';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function createPaymentIntent(TenantBillingPaymentIntent $intent, array $context = []): PaymentIntentResult
    {
        // Derived from the intent id so it is deterministic per intent AND unique
        // across intents (a retry after failure gets a distinct reference,
        // satisfying provider-reference uniqueness — PGW-R012/R018).
        $seed = ($intent->id ?? 0).':'.$intent->invoice_id.':'.$this->key().':'.$intent->channel;
        $reference = self::REFERENCE_PREFIX.strtoupper(substr(hash('sha256', $seed), 0, 16));

        return new PaymentIntentResult(
            providerReference: $reference,
            status: TenantBillingPaymentIntent::STATUS_PENDING,
            amount: (int) $intent->amount,
            currency: (string) $intent->currency,
            qrPayload: '00020101021126'.substr(hash('sha256', $reference), 0, 24), // deterministic pseudo-QR, non-secret
            expiresAt: null,
            metadata: ['mock' => true, 'channel' => $intent->channel],
        );
    }

    public function verifyWebhookSignature(array $payload, array $headers): WebhookVerification
    {
        $provided = $this->headerSignature($headers) ?? (string) ($payload['signature'] ?? '');
        $expected = $this->computeSignature($payload);

        if ($provided === '') {
            return new WebhookVerification(false, null, 'missing_signature');
        }

        $verified = hash_equals($expected, $provided);

        return new WebhookVerification(
            verified: $verified,
            signatureHash: substr(hash('sha256', $provided), 0, 32),
            reason: $verified ? null : 'invalid_signature',
        );
    }

    public function normalizeWebhook(array $payload): NormalizedWebhookEvent
    {
        $rawStatus = strtolower((string) ($payload['status'] ?? $payload['transaction_status'] ?? 'unknown'));
        $map = (array) config('payment_gateway_governance.event_status_map', []);
        $normalized = (string) ($map[$rawStatus] ?? 'unknown');

        return new NormalizedWebhookEvent(
            eventType: (string) ($payload['event_type'] ?? 'payment.'.$rawStatus),
            normalizedStatus: $normalized,
            providerEventId: isset($payload['event_id']) ? (string) $payload['event_id'] : null,
            providerReference: isset($payload['reference']) ? (string) $payload['reference'] : null,
            amount: isset($payload['amount']) ? (int) $payload['amount'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            occurredAt: isset($payload['occurred_at']) ? (string) $payload['occurred_at'] : null,
            metadata: ['raw_status' => $rawStatus],
        );
    }

    public function signForTesting(array $payload): string
    {
        return $this->computeSignature($payload);
    }

    /**
     * Deterministic HMAC over the payload EXCLUDING any signature field, so a
     * caller can sign then attach the signature.
     *
     * @param  array<string, mixed>  $payload
     */
    private function computeSignature(array $payload): string
    {
        unset($payload['signature']);
        ksort($payload);

        return hash_hmac('sha256', (string) json_encode($payload), self::MOCK_SIGNING_KEY);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function headerSignature(array $headers): ?string
    {
        foreach (['x-signature', 'x-callback-signature', 'signature'] as $name) {
            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === $name) {
                    return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
                }
            }
        }

        return null;
    }
}
