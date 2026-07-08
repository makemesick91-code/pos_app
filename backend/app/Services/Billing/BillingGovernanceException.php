<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Sprint 30 — raised when a billing operation would violate a governance rule
 * (e.g. generating an invoice for a tenant with no plan pricing, an invalid
 * invoice status transition, or a payment that would overstate revenue). Carries
 * a stable machine-readable code so callers/tests can assert on it.
 */
class BillingGovernanceException extends RuntimeException
{
    public function __construct(
        public readonly string $governanceCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
