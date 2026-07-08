<?php

namespace App\Services\PaymentGateway\Data;

/**
 * Sprint 31 — normalized result of a provider createPaymentIntent() call. Carries
 * only safe, non-secret fields: a deterministic provider reference, the intent
 * status, an optional QR/action payload string, and expiry. Never a credential.
 */
class PaymentIntentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $providerReference,
        public readonly string $status,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $qrPayload = null,
        public readonly ?string $expiresAt = null,
        public readonly array $metadata = [],
    ) {}
}
