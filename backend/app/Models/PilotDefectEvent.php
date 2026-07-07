<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable, append-only lifecycle event on a pilot defect (Sprint 17). Rows
 * are never updated or deleted — they are the defect's audit history. Payloads
 * must never store secrets.
 */
class PilotDefectEvent extends Model
{
    public const TYPE_CREATED = 'CREATED';
    public const TYPE_UPDATED = 'UPDATED';
    public const TYPE_ASSIGNED = 'ASSIGNED';
    public const TYPE_STATUS_CHANGED = 'STATUS_CHANGED';
    public const TYPE_SEVERITY_CHANGED = 'SEVERITY_CHANGED';
    public const TYPE_SLA_BREACHED = 'SLA_BREACHED';
    public const TYPE_ACCEPTED_RISK = 'ACCEPTED_RISK';
    public const TYPE_FIXED = 'FIXED';
    public const TYPE_RETEST_REQUESTED = 'RETEST_REQUESTED';
    public const TYPE_VERIFIED = 'VERIFIED';
    public const TYPE_CLOSED = 'CLOSED';
    public const TYPE_COMMENTED = 'COMMENTED';

    protected $fillable = [
        'pilot_defect_id',
        'actor_user_id',
        'event_type',
        'from_status',
        'to_status',
        'from_severity',
        'to_severity',
        'message',
        'payload',
        'evidence_reference',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(PilotDefect::class, 'pilot_defect_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
