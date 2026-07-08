<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription renewal run (Sprint 24). Evaluates existing TenantSubscription
 * records into candidates and summarizes readiness. A run NEVER charges, renews,
 * or suspends automatically.
 */
class SubscriptionRenewalRun extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED_MANUAL_REVIEW = 'FAILED_MANUAL_REVIEW';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED_MANUAL_REVIEW,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'run_reference',
        'policy_id',
        'status',
        'run_date',
        'period_start',
        'period_end',
        'summary',
        'created_by_user_id',
        'started_at',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRenewalPolicy::class, 'policy_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(SubscriptionRenewalCandidate::class, 'run_id');
    }
}
