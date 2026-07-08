<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS billing collection activity (Sprint 23). Manual collection governance
 * only: WHATSAPP_MANUAL / EMAIL_MANUAL are notes, never real sending. No secrets
 * stored.
 */
class SaasBillingCollectionActivity extends Model
{
    public const TYPE_NOTE = 'NOTE';
    public const TYPE_CALL = 'CALL';
    public const TYPE_WHATSAPP_MANUAL = 'WHATSAPP_MANUAL';
    public const TYPE_EMAIL_MANUAL = 'EMAIL_MANUAL';
    public const TYPE_INVOICE_ISSUED = 'INVOICE_ISSUED';
    public const TYPE_PAYMENT_FOLLOW_UP = 'PAYMENT_FOLLOW_UP';
    public const TYPE_PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    public const TYPE_DISPUTE_REVIEW = 'DISPUTE_REVIEW';
    public const TYPE_OVERDUE_REVIEW = 'OVERDUE_REVIEW';
    public const TYPE_COLLECTION_ESCALATION = 'COLLECTION_ESCALATION';

    /** @var array<int,string> */
    public const ACTIVITY_TYPES = [
        self::TYPE_NOTE,
        self::TYPE_CALL,
        self::TYPE_WHATSAPP_MANUAL,
        self::TYPE_EMAIL_MANUAL,
        self::TYPE_INVOICE_ISSUED,
        self::TYPE_PAYMENT_FOLLOW_UP,
        self::TYPE_PAYMENT_REVIEW,
        self::TYPE_DISPUTE_REVIEW,
        self::TYPE_OVERDUE_REVIEW,
        self::TYPE_COLLECTION_ESCALATION,
    ];

    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_DONE = 'DONE';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SKIPPED = 'SKIPPED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
        self::STATUS_SKIPPED,
    ];

    /** Activity types that name a channel but must NEVER send a real message. */
    public const MANUAL_ONLY_TYPES = [
        self::TYPE_WHATSAPP_MANUAL,
        self::TYPE_EMAIL_MANUAL,
    ];

    protected $fillable = [
        'activity_reference',
        'billing_account_id',
        'invoice_id',
        'actor_user_id',
        'activity_type',
        'status',
        'summary',
        'notes',
        'scheduled_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SaasBillingAccount::class, 'billing_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasBillingInvoice::class, 'invoice_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
