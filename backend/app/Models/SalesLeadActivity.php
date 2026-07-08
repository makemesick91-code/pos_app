<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sales lead activity (Sprint 22). WHATSAPP_MANUAL and EMAIL_MANUAL are MANUAL
 * NOTES only — no real message is ever sent and no external CRM/webhook is ever
 * called. No secrets are stored.
 */
class SalesLeadActivity extends Model
{
    public const TYPE_NOTE = 'NOTE';
    public const TYPE_CALL = 'CALL';
    public const TYPE_WHATSAPP_MANUAL = 'WHATSAPP_MANUAL';
    public const TYPE_EMAIL_MANUAL = 'EMAIL_MANUAL';
    public const TYPE_DEMO = 'DEMO';
    public const TYPE_PROPOSAL = 'PROPOSAL';
    public const TYPE_FOLLOW_UP = 'FOLLOW_UP';
    public const TYPE_STATUS_CHANGE = 'STATUS_CHANGE';
    public const TYPE_ASSIGNMENT = 'ASSIGNMENT';
    public const TYPE_QUALIFICATION = 'QUALIFICATION';
    public const TYPE_RISK_REVIEW = 'RISK_REVIEW';
    public const TYPE_ONBOARDING_HANDOVER_REVIEW = 'ONBOARDING_HANDOVER_REVIEW';

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
        self::TYPE_DEMO,
        self::TYPE_PROPOSAL,
        self::TYPE_FOLLOW_UP,
        self::TYPE_STATUS_CHANGE,
        self::TYPE_ASSIGNMENT,
        self::TYPE_QUALIFICATION,
        self::TYPE_RISK_REVIEW,
        self::TYPE_ONBOARDING_HANDOVER_REVIEW,
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
        'sales_lead_id',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }
}
