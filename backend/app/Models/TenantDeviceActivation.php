<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 34 — a governed Android device/register activation (ADR-R002..R006).
 *
 * The activation token is stored only as a sha256 hash; the raw token never lands
 * in a column, a log, or any output. An ACTIVATED, non-revoked, non-expired
 * activation is what authorises sync/write from the paired registered device.
 */
class TenantDeviceActivation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'register_id',
        'device_id',
        'provisioning_run_id',
        'activation_status',
        'activation_token_hash',
        'device_fingerprint_hash',
        'device_label',
        'app_version',
        'installation_id_hash',
        'attempt_count',
        'activated_by_user_id',
        'activated_at',
        'revoked_at',
        'expires_at',
        'last_seen_at',
        'failure_reason',
        'revocation_reason',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'attempt_count' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RegisteredDevice::class, 'device_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isActivated(): bool
    {
        return $this->activation_status === self::STATUS_ACTIVATED;
    }

    public function isRevoked(): bool
    {
        return $this->activation_status === self::STATUS_REVOKED;
    }

    public function isExpired(): bool
    {
        if ($this->activation_status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** An activation that may still authorise sync/write. */
    public function isUsable(): bool
    {
        return $this->isActivated() && ! $this->isRevoked() && ! $this->isExpired();
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Safe, redacted representation for API/CLI/admin output (ADR-R020/R022). The
     * token hash and fingerprint hash are deliberately NOT exposed.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'register_id' => $this->register_id,
            'device_id' => $this->device_id,
            'status' => $this->activation_status,
            'device_label' => $this->device_label,
            'app_version' => $this->app_version,
            'revocation_reason' => $this->revocation_reason,
            'activated_at' => optional($this->activated_at)->toIso8601String(),
            'revoked_at' => optional($this->revoked_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)->toIso8601String(),
        ];
    }
}
