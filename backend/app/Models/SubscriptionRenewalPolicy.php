<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription renewal policy (Sprint 24). Governance only — controls renewal
 * window, grace period, dunning start window and manual approval requirement. A
 * policy NEVER triggers real sending, auto-charge, or auto-suspension.
 */
class SubscriptionRenewalPolicy extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'policy_reference',
        'code',
        'name',
        'description',
        'status',
        'renewal_window_days',
        'grace_period_days',
        'dunning_start_days_before_expiry',
        'max_manual_dunning_notices',
        'requires_manual_approval',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'renewal_window_days' => 'integer',
            'grace_period_days' => 'integer',
            'dunning_start_days_before_expiry' => 'integer',
            'max_manual_dunning_notices' => 'integer',
            'requires_manual_approval' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
