<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 30 — a tenant billing invoice generated from the tenant's active plan
 * pricing for a canonical billing period. `status` is the document lifecycle;
 * `collection_state` is the payment axis. See config/billing_governance.php.
 */
class TenantBillingInvoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOID = 'void';

    public const STATUS_CANCELLED = 'cancelled';

    public const COLLECTION_NOT_DUE = 'not_due';

    public const COLLECTION_PENDING = 'pending';

    public const COLLECTION_PAID = 'paid';

    public const COLLECTION_FAILED = 'failed';

    public const COLLECTION_OVERDUE = 'overdue';

    public const COLLECTION_WRITTEN_OFF = 'written_off';

    public const COLLECTION_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'tenant_plan_id',
        'plan_key',
        'invoice_number',
        'period_key',
        'period_start',
        'period_end',
        'issued_at',
        'due_at',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'status',
        'collection_state',
        'source',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TenantPlan::class, 'tenant_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TenantBillingPayment::class, 'invoice_id');
    }

    /**
     * Amount confirmed/recorded against this invoice (failed/cancelled excluded).
     * This is the authoritative collected total and never overstates revenue.
     */
    public function collectedAmount(): int
    {
        return (int) $this->payments()
            ->whereIn('status', [TenantBillingPayment::STATUS_RECORDED, TenantBillingPayment::STATUS_CONFIRMED])
            ->sum('amount');
    }

    public function outstandingAmount(): int
    {
        return max(0, (int) $this->total_amount - $this->collectedAmount());
    }

    public function isPaid(): bool
    {
        return $this->collection_state === self::COLLECTION_PAID;
    }
}
