<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales lead (Sprint 22). May be imported from a Sprint 21 lead interest
 * submission or created manually. Intake/pipeline data ONLY: a sales lead NEVER
 * creates a tenant, user, subscription, or device and NEVER triggers real
 * billing/CRM/messaging. ready_for_onboarding_at means a manual onboarding review
 * is due, not automatic provisioning. No secrets are stored.
 */
class SalesLead extends Model
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_CONTACTED = 'CONTACTED';
    public const STATUS_QUALIFIED = 'QUALIFIED';
    public const STATUS_DEMO_SCHEDULED = 'DEMO_SCHEDULED';
    public const STATUS_PROPOSAL_SENT = 'PROPOSAL_SENT';
    public const STATUS_NEGOTIATION = 'NEGOTIATION';
    public const STATUS_WON_READY_FOR_ONBOARDING = 'WON_READY_FOR_ONBOARDING';
    public const STATUS_LOST = 'LOST';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_SPAM = 'SPAM';

    public const PRIORITY_LOW = 'LOW';
    public const PRIORITY_NORMAL = 'NORMAL';
    public const PRIORITY_HIGH = 'HIGH';
    public const PRIORITY_URGENT = 'URGENT';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_REVIEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_DEMO_SCHEDULED,
        self::STATUS_PROPOSAL_SENT,
        self::STATUS_NEGOTIATION,
        self::STATUS_WON_READY_FOR_ONBOARDING,
        self::STATUS_LOST,
        self::STATUS_ARCHIVED,
        self::STATUS_SPAM,
    ];

    /** @var array<int,string> */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected $fillable = [
        'lead_reference',
        'lead_interest_submission_id',
        'pipeline_stage_id',
        'status',
        'source',
        'business_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'business_type',
        'estimated_store_count',
        'estimated_device_count',
        'interest_package_code',
        'qualification_score',
        'priority',
        'assigned_to_user_id',
        'qualified_at',
        'lost_at',
        'lost_reason',
        'ready_for_onboarding_at',
        'evidence_reference',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'estimated_store_count' => 'integer',
            'estimated_device_count' => 'integer',
            'qualification_score' => 'integer',
            'qualified_at' => 'datetime',
            'lost_at' => 'datetime',
            'ready_for_onboarding_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function interestSubmission(): BelongsTo
    {
        return $this->belongsTo(LeadInterestSubmission::class, 'lead_interest_submission_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(SalesPipelineStage::class, 'pipeline_stage_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SalesLeadActivity::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SalesLeadAssignment::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(SalesPipelineRisk::class);
    }

    public function scopeReadyForOnboarding(Builder $query): Builder
    {
        return $query->whereNotNull('ready_for_onboarding_at');
    }
}
