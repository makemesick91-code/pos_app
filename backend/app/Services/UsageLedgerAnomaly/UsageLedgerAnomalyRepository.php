<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Models\TenantUsageEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sprint 28 — read-only query surface over the append-only usage event ledger for
 * anomaly detection (ULR-R001, ULR-R002). Every method here is a pure read: it
 * never updates, deletes, or appends a ledger event. The interpretation
 * (severity, redaction, repairability) lives in the detector.
 */
class UsageLedgerAnomalyRepository
{
    /** Metadata key fragments that must never appear in a usage event (ULR-R006). */
    public const DANGEROUS_METADATA_FRAGMENTS = [
        'password', 'token', 'secret', 'credential', 'authorization',
        'card', 'cvv', 'payment_key', 'api_key', 'private_key', 'pin', 'otp',
    ];

    private function base(?int $tenantId, ?string $meterKey): Builder
    {
        return TenantUsageEvent::query()
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->when($meterKey !== null, fn (Builder $q) => $q->where('meter_key', $meterKey));
    }

    /**
     * Groups of events that share tenant + meter + period + request_fingerprint
     * with more than one row — i.e. genuine double-count drift that inflates a
     * usage meter (the DB unique (tenant_id, idempotency_key) already blocks exact
     * idempotency-key dupes, so drift shows up as distinct keys, same fingerprint).
     *
     * @return Collection<int, object>
     */
    public function duplicateFingerprintGroups(?int $tenantId, ?string $meterKey): Collection
    {
        return $this->base($tenantId, $meterKey)
            ->whereNotNull('meter_key')
            ->whereNotNull('request_fingerprint')
            ->where('request_fingerprint', '!=', '')
            ->selectRaw('tenant_id, meter_key, period_key, request_fingerprint, '
                .'count(*) as events, coalesce(sum(quantity),0) as quantity, min(id) as keep_id')
            ->groupBy('tenant_id', 'meter_key', 'period_key', 'request_fingerprint')
            ->havingRaw('count(*) > 1')
            ->get();
    }

    /**
     * Events missing a structurally required field.
     *
     * @return Collection<int, TenantUsageEvent>
     */
    public function missingRequiredFieldEvents(?int $tenantId, ?string $meterKey): Collection
    {
        return $this->base($tenantId, $meterKey)
            ->where(function (Builder $q) {
                $q->whereNull('event_key')->orWhere('event_key', '=', '')
                    ->orWhereNull('event_category')->orWhere('event_category', '=', '')
                    ->orWhereNull('period_key')->orWhere('period_key', '=', '')
                    ->orWhereNull('occurred_at')
                    ->orWhere('meter_key', '=', '');
            })
            ->get(['id', 'tenant_id', 'event_key', 'event_category', 'meter_key', 'period_key', 'occurred_at', 'quantity']);
    }

    /**
     * Events with a non-positive quantity (0 or, defensively, any < 1).
     *
     * @return Collection<int, TenantUsageEvent>
     */
    public function invalidQuantityEvents(?int $tenantId, ?string $meterKey): Collection
    {
        return $this->base($tenantId, $meterKey)
            ->where('quantity', '<', 1)
            ->get(['id', 'tenant_id', 'meter_key', 'period_key', 'quantity']);
    }

    /**
     * Metered events, minimal columns, for server-side period-key validation.
     *
     * @return Collection<int, TenantUsageEvent>
     */
    public function meteredEvents(?int $tenantId, ?string $meterKey): Collection
    {
        return $this->base($tenantId, $meterKey)
            ->whereNotNull('meter_key')
            ->where('meter_key', '!=', '')
            ->get(['id', 'tenant_id', 'meter_key', 'period_key', 'occurred_at']);
    }

    /**
     * Distinct non-null meter keys present in the ledger.
     *
     * @return array<int, string>
     */
    public function distinctMeterKeys(?int $tenantId, ?string $meterKey): array
    {
        return $this->base($tenantId, $meterKey)
            ->whereNotNull('meter_key')
            ->where('meter_key', '!=', '')
            ->distinct()
            ->pluck('meter_key')
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    /**
     * Events whose raw metadata column contains a dangerous key fragment. Only
     * ids/keys are returned so a caller can never accidentally surface a secret
     * value (ULR-R006).
     *
     * @return Collection<int, TenantUsageEvent>
     */
    public function suspiciousMetadataEvents(?int $tenantId, ?string $meterKey): Collection
    {
        return $this->base($tenantId, $meterKey)
            ->whereNotNull('metadata')
            ->where(function (Builder $q) {
                foreach (self::DANGEROUS_METADATA_FRAGMENTS as $fragment) {
                    $q->orWhere('metadata', 'like', '%'.$fragment.'%');
                }
            })
            ->get(['id', 'tenant_id', 'meter_key', 'period_key', 'metadata']);
    }
}
