<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only production handover sign-off (Sprint 18). Preserves signer role,
 * decision, timestamp, notes, and evidence reference. Sign-off records are never
 * deleted. A REJECTED decision forces NO_GO; APPROVED_WITH_RISK forces WATCH.
 */
class ProductionHandoverSignoff extends Model
{
    public const ROLE_OWNER = 'OWNER';
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_OPERATOR = 'OPERATOR';
    public const ROLE_SUPPORT = 'SUPPORT';
    public const ROLE_TECHNICAL = 'TECHNICAL';

    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_APPROVED_WITH_RISK = 'APPROVED_WITH_RISK';
    public const DECISION_REJECTED = 'REJECTED';
    public const DECISION_PENDING = 'PENDING';

    /** @var array<int,string> */
    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_OPERATOR,
        self::ROLE_SUPPORT,
        self::ROLE_TECHNICAL,
    ];

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_APPROVED_WITH_RISK,
        self::DECISION_REJECTED,
        self::DECISION_PENDING,
    ];

    protected $fillable = [
        'production_handover_package_id',
        'signoff_reference',
        'signer_user_id',
        'signer_name',
        'signer_role',
        'decision',
        'notes',
        'evidence_reference',
        'signed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ProductionHandoverPackage::class, 'production_handover_package_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
