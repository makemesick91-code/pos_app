<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 34 — an Android sync batch idempotency record (ADR-R014). A replayed
 * batch (same tenant + client_batch_id or same idempotency_key) resumes this row
 * and never re-mutates.
 */
class TenantAndroidSyncBatch extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL_FAILED = 'partial_failed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REPLAYED = 'replayed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'register_id',
        'device_activation_id',
        'cashier_user_id',
        'client_batch_id',
        'idempotency_key',
        'status',
        'item_count',
        'accepted_count',
        'rejected_count',
        'duplicate_count',
        'conflict_count',
        'failed_count',
        'started_at',
        'completed_at',
        'failure_reason',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'item_count' => 'integer',
            'accepted_count' => 'integer',
            'rejected_count' => 'integer',
            'duplicate_count' => 'integer',
            'conflict_count' => 'integer',
            'failed_count' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantAndroidSyncItem::class, 'sync_batch_id');
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(TenantDeviceActivation::class, 'device_activation_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'register_id' => $this->register_id,
            'client_batch_id' => $this->client_batch_id,
            'status' => $this->status,
            'item_count' => $this->item_count,
            'accepted_count' => $this->accepted_count,
            'rejected_count' => $this->rejected_count,
            'duplicate_count' => $this->duplicate_count,
            'conflict_count' => $this->conflict_count,
            'failed_count' => $this->failed_count,
            'completed_at' => optional($this->completed_at)->toIso8601String(),
        ];
    }
}
