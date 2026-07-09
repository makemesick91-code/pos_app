<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 36 — an aggregate, redacted health snapshot (OBS-R003/R004/R020).
 * Stores only safe aggregate metrics; never raw payloads or PII.
 */
class ObservabilityHealthSnapshot extends Model
{
    public const SCOPE_APPLICATION = 'application';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_QUEUE = 'queue';
    public const SCOPE_SCHEDULER = 'scheduler';

    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WATCH = 'watch';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_CRITICAL = 'critical';

    protected $fillable = [
        'scope_type',
        'tenant_id',
        'status',
        'reason_code',
        'summary_safe',
        'metrics_json',
        'checked_at',
        'metadata_json',
    ];

    protected $casts = [
        'metrics_json' => 'array',
        'metadata_json' => 'array',
        'checked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope_type', $scope);
    }
}
