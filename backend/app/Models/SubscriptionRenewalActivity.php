<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription renewal activity (Sprint 24). Manual communication/review notes
 * only. A WHATSAPP_MANUAL / EMAIL_MANUAL activity is an internal record of an
 * external manual action — NO real message is ever sent.
 */
class SubscriptionRenewalActivity extends Model
{
    public const TYPE_NOTE = 'NOTE';
    public const TYPE_CALL = 'CALL';
    public const TYPE_WHATSAPP_MANUAL = 'WHATSAPP_MANUAL';
    public const TYPE_EMAIL_MANUAL = 'EMAIL_MANUAL';
    public const TYPE_DUNNING_PREPARED = 'DUNNING_PREPARED';
    public const TYPE_DUNNING_MARKED_SENT_MANUALLY = 'DUNNING_MARKED_SENT_MANUALLY';
    public const TYPE_PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    public const TYPE_BILLING_REVIEW = 'BILLING_REVIEW';
    public const TYPE_RENEWAL_REVIEW = 'RENEWAL_REVIEW';
    public const TYPE_MANUAL_RENEWAL_DECISION = 'MANUAL_RENEWAL_DECISION';
    public const TYPE_GRACE_REVIEW = 'GRACE_REVIEW';
    public const TYPE_OVERDUE_REVIEW = 'OVERDUE_REVIEW';
    public const TYPE_ESCALATION = 'ESCALATION';

    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_DONE = 'DONE';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SKIPPED = 'SKIPPED';

    /** @var array<int,string> */
    public const TYPES = [
        self::TYPE_NOTE,
        self::TYPE_CALL,
        self::TYPE_WHATSAPP_MANUAL,
        self::TYPE_EMAIL_MANUAL,
        self::TYPE_DUNNING_PREPARED,
        self::TYPE_DUNNING_MARKED_SENT_MANUALLY,
        self::TYPE_PAYMENT_REVIEW,
        self::TYPE_BILLING_REVIEW,
        self::TYPE_RENEWAL_REVIEW,
        self::TYPE_MANUAL_RENEWAL_DECISION,
        self::TYPE_GRACE_REVIEW,
        self::TYPE_OVERDUE_REVIEW,
        self::TYPE_ESCALATION,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
        self::STATUS_SKIPPED,
    ];

    protected $fillable = [
        'activity_reference',
        'candidate_id',
        'tenant_id',
        'tenant_subscription_id',
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

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalCandidate::class, 'candidate_id');
    }
}
