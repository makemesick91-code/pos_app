<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription dunning notice (Sprint 24). A MANUAL reminder queue record only.
 * MARKED_SENT_MANUALLY means an admin recorded an external manual action. No real
 * email/WhatsApp/SMS is ever sent and no secrets are stored.
 */
class SubscriptionDunningNotice extends Model
{
    public const TYPE_RENEWAL_REMINDER = 'RENEWAL_REMINDER';
    public const TYPE_PAYMENT_REMINDER = 'PAYMENT_REMINDER';
    public const TYPE_GRACE_NOTICE = 'GRACE_NOTICE';
    public const TYPE_OVERDUE_NOTICE = 'OVERDUE_NOTICE';
    public const TYPE_FINAL_MANUAL_REVIEW_NOTICE = 'FINAL_MANUAL_REVIEW_NOTICE';

    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_PREPARED = 'PREPARED';
    public const STATUS_MARKED_SENT_MANUALLY = 'MARKED_SENT_MANUALLY';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SKIPPED = 'SKIPPED';

    public const CHANNEL_WHATSAPP_MANUAL = 'WHATSAPP_MANUAL';
    public const CHANNEL_EMAIL_MANUAL = 'EMAIL_MANUAL';
    public const CHANNEL_CALL_MANUAL = 'CALL_MANUAL';
    public const CHANNEL_IN_APP_ADMIN_NOTE = 'IN_APP_ADMIN_NOTE';
    public const CHANNEL_OTHER_MANUAL = 'OTHER_MANUAL';

    /** @var array<int,string> */
    public const TYPES = [
        self::TYPE_RENEWAL_REMINDER,
        self::TYPE_PAYMENT_REMINDER,
        self::TYPE_GRACE_NOTICE,
        self::TYPE_OVERDUE_NOTICE,
        self::TYPE_FINAL_MANUAL_REVIEW_NOTICE,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_PREPARED,
        self::STATUS_MARKED_SENT_MANUALLY,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_SKIPPED,
    ];

    /** @var array<int,string> */
    public const CHANNELS = [
        self::CHANNEL_WHATSAPP_MANUAL,
        self::CHANNEL_EMAIL_MANUAL,
        self::CHANNEL_CALL_MANUAL,
        self::CHANNEL_IN_APP_ADMIN_NOTE,
        self::CHANNEL_OTHER_MANUAL,
    ];

    protected $fillable = [
        'notice_reference',
        'candidate_id',
        'tenant_id',
        'tenant_subscription_id',
        'billing_invoice_id',
        'notice_type',
        'status',
        'channel',
        'scheduled_for',
        'prepared_at',
        'marked_sent_manually_at',
        'completed_at',
        'actor_user_id',
        'summary',
        'message_template_key',
        'manual_message_preview',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'prepared_at' => 'datetime',
            'marked_sent_manually_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalCandidate::class, 'candidate_id');
    }
}
