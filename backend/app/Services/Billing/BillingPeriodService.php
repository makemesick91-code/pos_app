<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

/**
 * Sprint 30 — the ONLY place a billing period may be computed (BIL-R001).
 *
 * Controllers, commands, and the invoice generator all resolve periods through
 * here so the vocabulary (`period_key`, start/end, due date) is deterministic and
 * identical everywhere. Monthly only for the foundation. Given the same date and
 * config the result is byte-for-byte stable — no reliance on a random clock.
 */
class BillingPeriodService
{
    /**
     * Resolve the canonical billing period that CONTAINS the given date (default:
     * now). `period_key` is the civil month (YYYY-MM) in the billing timezone.
     */
    public function resolveForDate(?CarbonImmutable $date = null): BillingPeriod
    {
        $tz = (string) config('billing_governance.period.timezone', config('app.timezone', 'UTC'));
        $dueDays = (int) config('billing_governance.period.due_days', 7);
        $interval = (string) config('billing_governance.period.interval', 'monthly');

        $anchor = ($date ?? CarbonImmutable::now())->setTimezone($tz);

        $start = $anchor->startOfMonth()->startOfDay();
        $end = $anchor->endOfMonth()->endOfDay();
        $dueAt = $start->addDays($dueDays)->endOfDay();

        return new BillingPeriod(
            key: $start->format('Y-m'),
            start: $start,
            end: $end,
            dueAt: $dueAt,
            interval: $interval,
        );
    }

    /**
     * Resolve a period from an explicit `YYYY-MM` key. Invalid keys are rejected
     * so a malformed period can never reach invoice generation.
     */
    public function resolveForKey(string $periodKey): BillingPeriod
    {
        if (preg_match('/^\d{4}-\d{2}$/', $periodKey) !== 1) {
            throw new BillingGovernanceException(
                'BILLING_INVALID_PERIOD',
                "Invalid billing period key '{$periodKey}'; expected format YYYY-MM.",
            );
        }

        $tz = (string) config('billing_governance.period.timezone', config('app.timezone', 'UTC'));

        $anchor = CarbonImmutable::createFromFormat('Y-m-d', $periodKey.'-01', $tz);

        if ($anchor === false) {
            throw new BillingGovernanceException(
                'BILLING_INVALID_PERIOD',
                "Invalid billing period key '{$periodKey}'.",
            );
        }

        return $this->resolveForDate($anchor);
    }
}
