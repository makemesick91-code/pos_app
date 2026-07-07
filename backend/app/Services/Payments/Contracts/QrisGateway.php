<?php

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\QrisCreateRequest;
use App\Services\Payments\Data\QrisCreateResponse;
use App\Services\Payments\Data\QrisWebhookPayload;

/**
 * Contract every QRIS provider implements. Keeps QrisPaymentService and
 * QrisWebhookService provider-agnostic so a real gateway can be swapped in
 * without touching the money-critical flow. Implementations must never leak
 * credentials into the returned data.
 */
interface QrisGateway
{
    /**
     * The provider code persisted on the payment (e.g. FAKE, MIDTRANS).
     */
    public function name(): string;

    public function create(QrisCreateRequest $request): QrisCreateResponse;

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhook(array $headers, array $payload, string $rawBody): bool;

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $headers, array $payload, string $rawBody): QrisWebhookPayload;
}
