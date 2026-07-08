<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS billing manual payment evidence (Sprint 23). Manual evidence only:
 * MANUAL_QRIS_REFERENCE is a label, never a QRIS API call. An ACCEPTED evidence
 * updates the invoice paid/remaining state through governance; a REJECTED evidence
 * never updates paid_amount. No payment gateway payloads or secrets stored.
 */
class SaasBillingPaymentEvidence extends Model
{
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_VOIDED = 'VOIDED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_VOIDED,
    ];

    public const METHOD_BANK_TRANSFER = 'BANK_TRANSFER';
    public const METHOD_CASH_DEPOSIT = 'CASH_DEPOSIT';
    public const METHOD_MANUAL_QRIS_REFERENCE = 'MANUAL_QRIS_REFERENCE';
    public const METHOD_OTHER_MANUAL = 'OTHER_MANUAL';

    /** @var array<int,string> */
    public const METHODS = [
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CASH_DEPOSIT,
        self::METHOD_MANUAL_QRIS_REFERENCE,
        self::METHOD_OTHER_MANUAL,
    ];

    // "evidence" is uncountable to Laravel's pluralizer, so pin the table name.
    protected $table = 'saas_billing_payment_evidences';

    protected $fillable = [
        'payment_reference',
        'invoice_id',
        'status',
        'payment_method',
        'amount',
        'paid_at',
        'received_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejected_reason',
        'evidence_label',
        'evidence_reference',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasBillingInvoice::class, 'invoice_id');
    }
}
