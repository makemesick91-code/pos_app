<?php

namespace App\Services\PaymentGateway;

use RuntimeException;

/**
 * Sprint 31 — raised when a payment gateway operation would violate a governance
 * rule (e.g. creating an intent for a paid invoice, an unsigned webhook, an
 * amount mismatch, or a duplicate settlement). Carries a stable machine-readable
 * code so callers/tests can assert on it. Never carries a secret value.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        public readonly string $governanceCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
