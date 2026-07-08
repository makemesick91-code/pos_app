<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 34 — the per-item result for an Android sync batch (ADR-R013/R016).
 * (sync_batch_id, client_item_id) is unique so a client item can never be
 * double-recorded within a batch.
 */
class TenantAndroidSyncItem extends Model
{
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_SALE = 'sale';
    public const TYPE_ORDER = 'order';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_CUSTOMER_SNAPSHOT = 'customer_snapshot';
    public const TYPE_INVENTORY_SNAPSHOT = 'inventory_snapshot';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'sync_batch_id',
        'tenant_id',
        'client_item_id',
        'item_type',
        'action',
        'status',
        'server_subject_type',
        'server_subject_id',
        'conflict_code',
        'failure_reason',
        'payload_hash',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'server_subject_id' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TenantAndroidSyncBatch::class, 'sync_batch_id');
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
            'client_item_id' => $this->client_item_id,
            'item_type' => $this->item_type,
            'action' => $this->action,
            'status' => $this->status,
            'server_subject_type' => $this->server_subject_type,
            'server_subject_id' => $this->server_subject_id,
            'conflict_code' => $this->conflict_code,
        ];
    }
}
