<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant/store-owned sale header. Totals are authoritative only when written by
 * the backend (never trusted from the client). Sprint 4 finalizes online CASH
 * sales; QRIS/webhook/offline sync are out of scope.
 */
class Sale extends Model
{
    use HasFactory;

    public const PAYMENT_STATUS_UNPAID = 'UNPAID';
    public const PAYMENT_STATUS_PENDING = 'PENDING';
    public const PAYMENT_STATUS_PAID = 'PAID';
    public const PAYMENT_STATUS_FAILED = 'FAILED';
    public const PAYMENT_STATUS_CANCELLED = 'CANCELLED';
    public const PAYMENT_STATUS_EXPIRED = 'EXPIRED';

    public const SYNC_STATUS_SYNCED = 'SYNCED';
    public const SYNC_STATUS_PENDING = 'PENDING_SYNC';
    public const SYNC_STATUS_FAILED = 'FAILED_SYNC';

    public const SOURCE_ANDROID_ONLINE = 'ANDROID_ONLINE';
    public const SOURCE_ANDROID_OFFLINE = 'ANDROID_OFFLINE';
    public const SOURCE_WEB_ADMIN = 'WEB_ADMIN';
    public const SOURCE_API = 'API';

    /**
     * Transient (non-persisted) flag set by SaleService when an offline submit is
     * an idempotent replay of an already-stored sale. Surfaced as
     * meta.idempotent_replay and never written to the database.
     */
    public bool $idempotentReplay = false;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'device_id',
        'cashier_id',
        'invoice_number',
        'sale_date',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_total',
        'change_total',
        'payment_status',
        'sync_status',
        'source',
        'client_reference',
        'client_created_at',
        'synced_at',
        'notes',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_total' => 'decimal:2',
            'client_created_at' => 'datetime',
            'synced_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancelled(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_CANCELLED;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
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
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_CANCELLED);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('sale_date', now()->toDateString());
    }
}
