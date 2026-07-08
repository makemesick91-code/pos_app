<?php

namespace App\Services\PaymentGateway\Contracts;

use App\Models\TenantBillingPaymentIntent;
use App\Services\PaymentGateway\Data\NormalizedWebhookEvent;
use App\Services\PaymentGateway\Data\PaymentIntentResult;
use App\Services\PaymentGateway\Data\WebhookVerification;

/**
 * Sprint 31 — provider-neutral payment gateway contract. A real QRIS provider
 * (Midtrans/Xendit/…) or the deterministic mock implements this. The intent/
 * webhook/settlement services depend ONLY on this contract, never a concrete
 * provider, so a real gateway can be wired later without touching governance.
 */
interface PaymentGatewayProviderContract
{
    /** Stable provider key (e.g. `mock`, `midtrans`). */
    public function key(): string;

    /** Whether this provider performs real (network) gateway calls. */
    public function isLive(): bool;

    /**
     * Create a provider-side payment intent for an invoice/channel. MUST return a
     * deterministic, non-secret result (the mock is deterministic — PGW-R018).
     *
     * @param  array<string, mixed>  $context
     */
    public function createPaymentIntent(TenantBillingPaymentIntent $intent, array $context = []): PaymentIntentResult;

    /**
     * Verify a webhook signature (PGW-R007). MUST return a verdict + truncated
     * fingerprint, never the raw signature/secret.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(array $payload, array $headers): WebhookVerification;

    /**
     * Normalize a raw provider payload into the canonical event shape.
     *
     * @param  array<string, mixed>  $payload
     */
    public function normalizeWebhook(array $payload): NormalizedWebhookEvent;

    /**
     * Deterministically SIGN a payload for tests/smoke (mock only). Real providers
     * throw — signing is the provider's responsibility, not ours.
     *
     * @param  array<string, mixed>  $payload
     */
    public function signForTesting(array $payload): string;
}
