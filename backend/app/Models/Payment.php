<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant/store-owned payment record. Sprint 4 runtime writes CASH / MANUAL /
 * PAID rows; Sprint 5 adds backend-driven QRIS rows (provider FAKE/MIDTRANS/
 * XENDIT/DUITKU, status PENDING → PAID/FAILED/EXPIRED/CANCELLED) driven by the
 * gateway abstraction. Gateway secrets are never stored on the model; the raw
 * gateway response stays in the hidden `raw_response` column.
 */
class Payment extends Model
{
    use HasFactory;

    public const METHOD_CASH = 'CASH';
    public const METHOD_QRIS = 'QRIS';

    public const PROVIDER_MANUAL = 'MANUAL';
    public const PROVIDER_FAKE = 'FAKE';
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
        'qr_payload',
        'qr_image_url',
        'payment_url',
        'metadata',
        'paid_at',
        'expired_at',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
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

    public function scopeQris(Builder $query): Builder
    {
        return $query->where('method', self::METHOD_QRIS);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isQris(): bool
    {
        return $this->method === self::METHOD_QRIS;
    }

    public function webhookLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class);
    }
}
