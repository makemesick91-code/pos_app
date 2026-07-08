<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 28 — a governed usage-ledger repair record (ULR-R010).
 *
 * Written only by the governed `usage-ledger:repair-apply` command; there is no
 * runtime route that creates/updates/deletes it (ULR-R009). It never mutates the
 * append-only tenant_usage_events ledger — instead it carries a signed
 * quantity_delta that adjusts EFFECTIVE usage for a (tenant, meter, period). The
 * meter derives effective usage as ledger count + repair deltas, clamped at zero
 * (ULR-R013). repair_key is unique per tenant so re-applying is idempotent
 * (ULR-R011). Metadata is always redacted and never carries secrets (ULR-R006).
 */
class TenantUsageLedgerRepair extends Model
{
    public const TYPE_DUPLICATE_USAGE_CORRECTION = 'duplicate_usage_correction';

    protected $fillable = [
        'tenant_id',
        'meter_key',
        'period_key',
        'repair_key',
        'repair_type',
        'quantity_delta',
        'reason',
        'applied_by',
        'applied_at',
        'dry_run_payload',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'quantity_delta' => 'integer',
            'dry_run_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMeter(Builder $query, string $meterKey): Builder
    {
        return $query->where('meter_key', $meterKey);
    }

    public function scopeForPeriod(Builder $query, string $periodKey): Builder
    {
        return $query->where('period_key', $periodKey);
    }
}
