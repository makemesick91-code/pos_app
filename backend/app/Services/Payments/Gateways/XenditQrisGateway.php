<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payment;
use App\Services\Payments\Contracts\QrisGateway;
use App\Services\Payments\Data\QrisCreateRequest;
use App\Services\Payments\Data\QrisCreateResponse;
use App\Services\Payments\Data\QrisWebhookPayload;
use App\Services\Payments\Exceptions\PaymentGatewayException;

/**
 * Xendit QRIS provider — STUB. Config/credentials are read from env but no live
 * API call is implemented in Sprint 5. Throws a clear exception until merchant
 * onboarding is complete. It never performs an external network call in tests.
 */
class XenditQrisGateway implements QrisGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return Payment::PROVIDER_XENDIT;
    }

    public function create(QrisCreateRequest $request): QrisCreateResponse
    {
        throw PaymentGatewayException::notImplemented($this->name());
    }

    public function verifyWebhook(array $headers, array $payload, string $rawBody): bool
    {
        // Xendit sends an x-callback-token header; validated at onboarding.
        return false;
    }

    public function parseWebhook(array $headers, array $payload, string $rawBody): QrisWebhookPayload
    {
        throw PaymentGatewayException::notImplemented($this->name());
    }
}
