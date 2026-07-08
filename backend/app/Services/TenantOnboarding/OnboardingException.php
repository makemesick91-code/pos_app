<?php

namespace App\Services\TenantOnboarding;

use RuntimeException;

/**
 * Sprint 33 — a governed onboarding failure. Carries a stable, PII-free reason
 * code so callers/tests can assert the failure closed correctly (ONB-R003/R020).
 */
class OnboardingException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
