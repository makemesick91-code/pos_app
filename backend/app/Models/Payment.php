<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant/store-owned payment record. Sprint 4 runtime only ever writes CASH /
 * MANUAL / PAID rows; QRIS + gateway providers exist as constants for later
 * sprints but are never driven by gateway logic here.
 */
class Payment extends Model
{
    use HasFactory;

    public const METHOD_CASH = 'CASH';
    public const METHOD_QRIS = 'QRIS';

    public const PROVIDER_MANUAL = 'MANUAL';
    public const PROVIDER_MIDTRANS = 'MIDTRANS';
    public const PROVIDER_XENDIT = 'XENDIT';
    public const PROVIDER_DUITKU = 'DUITKU';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'sale_id',
        'method',
        'amount',
        'status',
        'provider',
        'provider_reference',
        'paid_at',
        'expired_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'raw_response',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }
}
