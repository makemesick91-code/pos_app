<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sprint 35 — a support-safe read-only context session (SUP-R017/R018/R019).
 *
 * Time-bound (`expires_at`) and tenant-scoped. NEVER holds a raw credential/token;
 * `scope_json`/`metadata_json` are redacted/safe.
 */
class TenantSupportSession extends Model
{
    public const TYPE_READ_ONLY_CONTEXT = 'read_only_context';
    public const TYPE_IMPERSONATION = 'impersonation';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ENDED = 'ended';
    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'session_type',
        'status',
        'reason_code',
        'starts_at',
        'expires_at',
        'ended_at',
        'scope_json',
        'metadata_json',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'scope_json' => 'array',
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * A session is effective only while ACTIVE and not past its expiry.
     */
    public function isEffective(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'actor_user_id' => $this->actor_user_id,
            'session_type' => $this->session_type,
            'status' => $this->effectiveStatus(),
            'reason_code' => $this->reason_code,
            'read_only' => true,
            'starts_at' => optional($this->starts_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'ended_at' => optional($this->ended_at)->toIso8601String(),
        ];
    }

    /**
     * Present an expired-but-still-ACTIVE row as EXPIRED without a background job
     * (SUP-R017 — expiry is enforced by query/service check).
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_ACTIVE && $this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }
}
