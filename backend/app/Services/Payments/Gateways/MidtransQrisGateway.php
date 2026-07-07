<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\QrisGateway;
use App\Services\Payments\Data\QrisCreateRequest;
use App\Services\Payments\Data\QrisCreateResponse;
use App\Services\Payments\Data\QrisWebhookPayload;
use App\Services\Payments\Exceptions\PaymentGatewayException;

/**
 * Midtrans QRIS provider — STUB. Wiring exists (config + credentials read from
 * env) but no live API call is implemented in Sprint 5. Until merchant
 * onboarding is complete this throws a clear exception rather than pretending to
 * be production-ready. It never performs an external network call in tests.
 */
class MidtransQrisGateway implements QrisGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return Payment::PROVIDER_MIDTRANS;
    }

    public function create(QrisCreateRequest $request): QrisCreateResponse
    {
        throw PaymentGatewayException::notImplemented($this->name());
    }

    public function verifyWebhook(array $headers, array $payload, string $rawBody): bool
    {
        // Signature scheme (SHA-512 of order_id+status_code+gross_amount+server_key)
        // will be implemented at onboarding. Until then, never trust a payload.
        return false;
    }

    public function parseWebhook(array $headers, array $payload, string $rawBody): QrisWebhookPayload
    {
        throw PaymentGatewayException::notImplemented($this->name());
    }
}
