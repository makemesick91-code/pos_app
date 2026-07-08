<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription renewal decision (Sprint 24). Records governance. It NEVER
 * updates a TenantSubscription automatically; the only mutation path is the
 * explicit applyManualRenewalDecision action (RECORDED APPROVE_MANUAL_RENEWAL /
 * APPROVE_WITH_RISK only). Payment evidence NEVER auto-renews.
 */
class SubscriptionRenewalDecision extends Model
{
    public const DECISION_APPROVE_MANUAL_RENEWAL = 'APPROVE_MANUAL_RENEWAL';
    public const DECISION_APPROVE_WITH_RISK = 'APPROVE_WITH_RISK';
    public const DECISION_REJECT_RENEWAL = 'REJECT_RENEWAL';
    public const DECISION_DEFER_REVIEW = 'DEFER_REVIEW';
    public const DECISION_DO_NOT_RENEW = 'DO_NOT_RENEW';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_RECORDED = 'RECORDED';
    public const STATUS_APPLIED_MANUALLY = 'APPLIED_MANUALLY';
    public const STATUS_VOIDED = 'VOIDED';

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_APPROVE_MANUAL_RENEWAL,
        self::DECISION_APPROVE_WITH_RISK,
        self::DECISION_REJECT_RENEWAL,
        self::DECISION_DEFER_REVIEW,
        self::DECISION_DO_NOT_RENEW,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECORDED,
        self::STATUS_APPLIED_MANUALLY,
        self::STATUS_VOIDED,
    ];

    /** Decisions that permit an explicit manual apply. */
    public const APPLICABLE_DECISIONS = [
        self::DECISION_APPROVE_MANUAL_RENEWAL,
        self::DECISION_APPROVE_WITH_RISK,
    ];

    protected $fillable = [
        'decision_reference',
        'candidate_id',
        'tenant_id',
        'tenant_subscription_id',
        'decision',
        'status',
        'decided_by_user_id',
        'decided_at',
        'effective_start_date',
        'effective_end_date',
        'approved_plan_id',
        'manual_billing_invoice_id',
        'reason',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalCandidate::class, 'candidate_id');
    }

    public function tenantSubscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }
}
