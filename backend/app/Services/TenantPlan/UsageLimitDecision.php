<?php

namespace App\Services\TenantPlan;

/**
 * Sprint 26 — the immutable result of a usage-limit evaluation.
 *
 * Produced only by TenantUsageLimitService. Carries whether the requested usage
 * is allowed, the limit key, the numeric cap (null = unlimited or not
 * configured), current usage, remaining, whether the limit is meterable, and the
 * stable machine code for a denial (USAGE_LIMIT_EXCEEDED, TPE-R009). Never
 * carries secrets.
 */
final class UsageLimitDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $limitKey,
        public readonly bool $unlimited,
        public readonly ?int $limit,
        public readonly ?int $current,
        public readonly ?int $remaining,
        public readonly bool $meterable,
        public readonly ?string $code = null,
        public readonly string $period = 'lifetime',
    ) {}

    public function exceeded(): bool
    {
        return ! $this->allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limitKey,
            'allowed' => $this->allowed,
            'unlimited' => $this->unlimited,
            'limit_value' => $this->limit,
            'current' => $this->current,
            'remaining' => $this->remaining,
            'meterable' => $this->meterable,
            'period' => $this->period,
            'code' => $this->code,
        ];
    }
}
