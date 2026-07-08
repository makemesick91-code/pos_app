<?php

namespace App\Services\AndroidRuntime;

/**
 * Sprint 34 — a deterministic, safe Android runtime access decision (ADR-R016).
 *
 * `status` is one of: allowed, degraded, read_only, blocked, denied. Writes are
 * permitted only when `allowed` is true. `conflictCode` maps a denial to a stable
 * sync conflict code (config android_runtime_governance.conflict_codes). No field
 * ever carries a token/secret/PII.
 */
final class AndroidRuntimeDecision
{
    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_READ_ONLY = 'read_only';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DENIED = 'denied';

    public function __construct(
        public readonly bool $allowed,
        public readonly string $status,
        public readonly string $reasonCode,
        public readonly string $message,
        public readonly ?string $conflictCode = null,
        public readonly int $httpStatus = 200,
        public readonly ?string $billingState = null,
        public readonly ?string $planCode = null,
        public readonly array $metadata = [],
    ) {}

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    public function readOnly(): bool
    {
        return $this->status === self::STATUS_READ_ONLY;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'message' => $this->message,
            'conflict_code' => $this->conflictCode,
            'billing_state' => $this->billingState,
            'plan_code' => $this->planCode,
        ];
    }
}
