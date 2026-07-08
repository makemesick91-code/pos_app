<?php

namespace App\Services\AndroidRuntime;

use RuntimeException;

/**
 * Sprint 34 — a safe, redacted Android runtime failure. The message is a stable
 * reason code and NEVER contains a raw token, password, signature or PII
 * (ADR-R020/R021/R022).
 */
class AndroidRuntimeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reasonCode = 'ANDROID_RUNTIME_ERROR',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
