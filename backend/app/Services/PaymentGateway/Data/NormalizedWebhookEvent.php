<?php

namespace App\Services\PaymentGateway\Data;

/**
 * Sprint 31 — a provider-neutral, normalized view of a webhook payload. The
 * `normalizedStatus` is a canonical vocabulary value (paid|failed|expired|
 * cancelled|pending|requires_action) resolved via config('payment_gateway_
 * governance.event_status_map'). Carries no secret/signature.
 */
class NormalizedWebhookEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $normalizedStatus,
        public readonly ?string $providerEventId = null,
        public readonly ?string $providerReference = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $occurredAt = null,
        public readonly array $metadata = [],
    ) {}
}
