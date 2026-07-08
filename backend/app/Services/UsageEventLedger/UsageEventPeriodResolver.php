<?php

namespace App\Services\UsageEventLedger;

use Illuminate\Support\Carbon;

/**
 * Sprint 27 — resolves the stable, server-side period key for a usage meter
 * (UEL-R005). Monthly meters collapse to a `Y-m` key (e.g. 2026-07); lifetime
 * meters use the constant `lifetime`. The period is always derived from the
 * server clock, never from client input.
 */
class UsageEventPeriodResolver
{
    public const LIFETIME = 'lifetime';

    public function monthlyPeriodKey(?Carbon $at = null): string
    {
        return ($at ?? Carbon::now())->format('Y-m');
    }

    public function periodKeyFor(string $period, ?Carbon $at = null): string
    {
        return match ($period) {
            'monthly' => $this->monthlyPeriodKey($at),
            default => self::LIFETIME,
        };
    }
}
