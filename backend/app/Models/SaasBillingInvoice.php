<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SaaS billing invoice (Sprint 23). A platform-to-tenant billing invoice, NOT a
 * POS cashier receipt. Issuing it NEVER triggers a payment gateway and NEVER auto-
 * suspends a tenant. Totals are server-calculated from lines; paid/remaining are
 * only mutated through payment-evidence review governance. No secrets stored.
 */
class SaasBillingInvoice extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ISSUED = 'ISSUED';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_PAID = 'PAID';
    public const STATUS_OVERDUE = 'OVERDUE';
    public const STATUS_DISPUTED = 'DISPUTED';
    public const STATUS_VOIDED = 'VOIDED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_DISPUTED,
        self::STATUS_VOIDED,
        self::STATUS_ARCHIVED,
    ];

    /** Statuses that may still receive a manual payment evidence. */
    public const EVIDENCE_ALLOWED_STATUSES = [
        self::STATUS_ISSUED,
        self::STATUS_PARTIAL,
        self::STATUS_OVERDUE,
        self::STATUS_DISPUTED,
    ];

    protected $fillable = [
        'invoice_reference',
        'invoice_number',
        'billing_account_id',
        'tenant_id',
        'tenant_subscription_id',
        'billing_cycle_id',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'issued_by_user_id',
        'issued_at',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SaasBillingAccount::class, 'billing_account_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SaasBillingCycle::class, 'billing_cycle_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaasBillingInvoiceLine::class, 'invoice_id');
    }

    public function paymentEvidences(): HasMany
    {
        return $this->hasMany(SaasBillingPaymentEvidence::class, 'invoice_id');
    }

    public function canReceivePaymentEvidence(): bool
    {
        return in_array($this->status, self::EVIDENCE_ALLOWED_STATUSES, true);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
