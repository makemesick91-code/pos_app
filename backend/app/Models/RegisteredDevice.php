<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant-owned registered device (Sprint 10). Only ACTIVE devices count
 * against the plan's max_devices and may access protected business APIs. The
 * device_uuid is generated on the Android device; it never carries a password
 * or payment credential. See Sprint 10 evidence.
 */
class RegisteredDevice extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const PLATFORM_ANDROID = 'ANDROID';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'store_id',
        'device_uuid',
        'device_name',
        'platform',
        'app_version',
        'last_seen_at',
        'registered_at',
        'revoked_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
