<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingCycle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing cycle lifecycle.
 *
 * Creates a billing cycle and drives its conservative transitions
 * (DRAFT → OPEN → LOCKED → CLOSED). An invalid period (end before start) is
 * rejected. Summarizes cycles by status. No secrets stored.
 */
class BillingCycleService
{
    use SanitizesBillingCollectionText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SaasBillingCycle
    {
        $start = Carbon::parse((string) ($attributes['period_start'] ?? throw new InvalidArgumentException('period_start is required.')));
        $end = Carbon::parse((string) ($attributes['period_end'] ?? throw new InvalidArgumentException('period_end is required.')));

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Billing cycle period_end must not be before period_start.');
        }

        return SaasBillingCycle::query()->create([
            'cycle_reference' => (string) ($attributes['cycle_reference'] ?? $this->generateReference()),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SaasBillingCycle::STATUS_DRAFT)),
            'billing_month' => $this->sanitizeNullableString($attributes['billing_month'] ?? $start->format('Y-m')),
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SaasBillingCycle $cycle, array $attributes, ?User $actor = null): SaasBillingCycle
    {
        if (array_key_exists('period_start', $attributes) || array_key_exists('period_end', $attributes)) {
            $start = Carbon::parse((string) ($attributes['period_start'] ?? $cycle->period_start));
            $end = Carbon::parse((string) ($attributes['period_end'] ?? $cycle->period_end));
            if ($end->lt($start)) {
                throw new InvalidArgumentException('Billing cycle period_end must not be before period_start.');
            }
            $cycle->period_start = $start->toDateString();
            $cycle->period_end = $end->toDateString();
        }

        if (array_key_exists('billing_month', $attributes)) {
            $cycle->billing_month = $this->sanitizeNullableString($attributes['billing_month']);
        }
        if (array_key_exists('notes', $attributes)) {
            $cycle->notes = $this->sanitizeNullableString($attributes['notes']);
        }
        if (array_key_exists('metadata', $attributes)) {
            $cycle->metadata = $this->sanitizeArray($attributes['metadata']);
        }

        $cycle->save();

        return $cycle->refresh();
    }

    public function open(SaasBillingCycle $cycle): SaasBillingCycle
    {
        return $this->transition($cycle, SaasBillingCycle::STATUS_OPEN, [
            SaasBillingCycle::STATUS_DRAFT,
            SaasBillingCycle::STATUS_OPEN,
        ]);
    }

    public function lock(SaasBillingCycle $cycle): SaasBillingCycle
    {
        return $this->transition($cycle, SaasBillingCycle::STATUS_LOCKED, [
            SaasBillingCycle::STATUS_OPEN,
            SaasBillingCycle::STATUS_LOCKED,
        ]);
    }

    public function close(SaasBillingCycle $cycle): SaasBillingCycle
    {
        return $this->transition($cycle, SaasBillingCycle::STATUS_CLOSED, [
            SaasBillingCycle::STATUS_LOCKED,
            SaasBillingCycle::STATUS_CLOSED,
        ]);
    }

    /**
     * @param array<int,string> $allowedFrom
     */
    private function transition(SaasBillingCycle $cycle, string $to, array $allowedFrom): SaasBillingCycle
    {
        if (! in_array($cycle->status, $allowedFrom, true)) {
            throw new InvalidArgumentException("Cannot transition billing cycle from {$cycle->status} to {$to}.");
        }

        $cycle->status = $to;
        $cycle->save();

        return $cycle->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasBillingCycle::query()->get();

        $byStatus = [];
        foreach (SaasBillingCycle::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_cycles' => $all->count(),
            'by_status' => $byStatus,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SaasBillingCycle::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid billing cycle status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'CYCLE-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
