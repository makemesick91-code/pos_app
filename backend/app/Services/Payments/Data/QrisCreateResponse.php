<?php

namespace App\Services\Payments\Data;

use Carbon\CarbonInterface;

/**
 * Provider-agnostic result of a QRIS creation. Only carries data safe to persist
 * and (mostly) surface to Android — never provider secrets. `rawResponse` is
 * stored in the hidden payments.raw_response column for reconciliation only.
 */
class QrisCreateResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public readonly string $providerReference,
        public readonly string $qrPayload,
        public readonly ?string $qrImageUrl,
        public readonly ?string $paymentUrl,
        public readonly CarbonInterface $expiredAt,
        public readonly array $rawResponse = [],
    ) {}
}
