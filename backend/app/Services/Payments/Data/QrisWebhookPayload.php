<?php

namespace App\Services\Payments\Data;

use Carbon\CarbonInterface;

/**
 * Normalized view of an inbound webhook after a gateway has parsed it. `status`
 * is already mapped to a Payment::STATUS_* constant so the webhook service is
 * provider-independent.
 */
class QrisWebhookPayload
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $providerReference,
        public readonly string $status,
        public readonly ?string $eventType = null,
        public readonly ?CarbonInterface $paidAt = null,
        public readonly array $raw = [],
    ) {}
}
