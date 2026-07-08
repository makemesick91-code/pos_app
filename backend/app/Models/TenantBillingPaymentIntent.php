<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 31 — a request to pay a Sprint 30 tenant billing invoice through a
 * payment gateway/QRIS channel. Provider-neutral and idempotent per invoice +
 * provider + channel. Settlement flows through the Sprint 30 collection service,
 * never a direct invoice mutation. See config/payment_gateway_governance.php.
 */
class TenantBillingPaymentIntent extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses for which an intent is still awaiting settlement. */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_REQUIRES_ACTION];

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'provider',
        'channel',
        'period_key',
        'amount',
        'currency',
        'status',
        'provider_reference',
        'idempotency_key',
        'expires_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'created_by_user_id',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantBillingInvoice::class, 'invoice_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TenantBillingGatewayEvent::class, 'payment_intent_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
