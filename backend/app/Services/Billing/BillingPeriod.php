<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

/**
 * Sprint 30 — an immutable, canonical billing period value object (BIL-R001).
 *
 * A period is fully determined by its `key` (YYYY-MM) plus the configured due
 * policy; there is no ad-hoc arithmetic anywhere else. Boundaries are civil-day
 * aligned in the billing timezone so a period is stable regardless of server
 * locale or the exact instant it is resolved.
 */
final class BillingPeriod
{
    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $dueAt,
        public readonly string $interval,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'period_key' => $this->key,
            'period_start' => $this->start->toIso8601String(),
            'period_end' => $this->end->toIso8601String(),
            'due_at' => $this->dueAt->toIso8601String(),
            'interval' => $this->interval,
        ];
    }
}
