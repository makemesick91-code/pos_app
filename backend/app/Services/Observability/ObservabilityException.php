<?php

namespace App\Services\Observability;

use RuntimeException;

/**
 * Sprint 36 — governed observability failures (reason required, retry disabled,
 * unknown target). Carries a safe HTTP status; the message never contains a
 * secret or PII.
 */
class ObservabilityException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function reasonRequired(): self
    {
        return new self('A reason code is required for this observability action.', 422);
    }

    public static function invalidReasonCode(): self
    {
        return new self('The provided reason code is not a governed observability reason code.', 422);
    }

    public static function jobRetryDisabled(string $reason): self
    {
        return new self($reason, 409);
    }

    public static function jobNotIdempotent(): self
    {
        return new self('This failed job is not on the idempotency-safe allow-list and must not be retried.', 409);
    }
}
