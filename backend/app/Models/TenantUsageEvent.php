<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 27 — an append-only tenant usage event (UEL-R001, UEL-R002). Written
 * only through UsageEventRecorder/UsageEventLedgerService; normal runtime never
 * updates or deletes a persisted event. Monthly usage meters are derived by
 * counting events for a meter_key within a stable server-side period_key
 * (UEL-R005, UEL-R006). Metadata is always redacted before it reaches this model
 * (UEL-R003). Never carries secrets.
 */
class TenantUsageEvent extends Model
{
    public const EVENT_REPORT_EXPORTED = 'report.exported';

    public const CATEGORY_REPORT_EXPORT = 'report_export';

    public const METER_REPORTS_EXPORTS_MONTHLY = 'reports.exports.monthly';

    public const SOURCE_API = 'api';
    public const SOURCE_WEB = 'web';
    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'tenant_id',
        'event_key',
        'event_category',
        'meter_key',
        'quantity',
        'occurred_at',
        'period_key',
        'idempotency_key',
        'source',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'request_fingerprint',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'quantity' => 'integer',
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
