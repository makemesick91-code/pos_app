<?php

namespace App\Services\SupportOperations;

use RuntimeException;

/**
 * Sprint 35 — a governed support-operation refusal. Carries a stable, safe error
 * code and HTTP status so a denied support action fails closed with a clear,
 * PII-free message (SUP-R005/R014/R018).
 */
class SupportException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public static function reasonRequired(): self
    {
        return new self('SUPPORT_REASON_REQUIRED', 'A support action requires an explicit reason code.', 422);
    }

    public static function invalidReasonCode(): self
    {
        return new self('SUPPORT_INVALID_REASON_CODE', 'The reason code is not an allowed support reason code.', 422);
    }

    public static function impersonationDisabled(string $message): self
    {
        return new self('SUPPORT_IMPERSONATION_DISABLED', $message, 403);
    }

    public static function reactivationNotSupported(string $message): self
    {
        return new self('SUPPORT_REACTIVATION_NOT_SUPPORTED', $message, 409);
    }

    public static function tenantSuspended(): self
    {
        return new self('SUPPORT_TENANT_MANUALLY_SUSPENDED', 'The tenant is manually suspended; support actions cannot lift or bypass it.', 423);
    }

    public static function sessionExpired(): self
    {
        return new self('SUPPORT_SESSION_EXPIRED', 'The support session has expired.', 410);
    }
}
