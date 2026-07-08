<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 30 — a recorded payment fact against a tenant billing invoice. This is a
 * governance foundation, not a payment gateway integration. Failed/cancelled
 * payments never count toward collected revenue (BIL-R010). See
 * config/billing_governance.php.
 */
class TenantBillingPayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payment_reference',
        'amount',
        'currency',
        'method',
        'status',
        'collection_state',
        'received_at',
        'recorded_by_user_id',
        'source',
        'idempotency_key',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'received_at' => 'datetime',
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

    /** A payment that counts toward collected revenue. */
    public function counts(): bool
    {
        return in_array($this->status, [self::STATUS_RECORDED, self::STATUS_CONFIRMED], true);
    }
}
