<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sales lead assignment history row (Sprint 22). Internal sales ownership only —
 * never provisions anything and never bills.
 */
class SalesLeadAssignment extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REASSIGNED = 'REASSIGNED';
    public const STATUS_UNASSIGNED = 'UNASSIGNED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_REASSIGNED,
        self::STATUS_UNASSIGNED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'assignment_reference',
        'sales_lead_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'status',
        'assigned_at',
        'unassigned_at',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
