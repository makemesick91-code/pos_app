<?php

namespace App\Services\PaymentGateway\Data;

/**
 * Sprint 31 — the outcome of verifying a webhook signature (PGW-R007). Carries a
 * boolean verdict and a TRUNCATED signature fingerprint only — never the raw
 * signature/secret (PGW-R011).
 */
class WebhookVerification
{
    public function __construct(
        public readonly bool $verified,
        public readonly ?string $signatureHash = null,
        public readonly ?string $reason = null,
    ) {}
}
