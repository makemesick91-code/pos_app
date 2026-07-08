<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A subscription renewal sign-off (Sprint 24). A rejected sign-off forces NO-GO;
 * an approved-with-risk sign-off forces WATCH. No secrets stored.
 */
class SubscriptionRenewalSignoff extends Model
{
    public const ROLE_OWNER = 'OWNER';
    public const ROLE_FINANCE = 'FINANCE';
    public const ROLE_SALES = 'SALES';
    public const ROLE_OPERATIONS = 'OPERATIONS';
    public const ROLE_LEGAL_PRIVACY = 'LEGAL_PRIVACY';
    public const ROLE_TECHNICAL = 'TECHNICAL';
    public const ROLE_SUPPORT = 'SUPPORT';

    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_APPROVED_WITH_RISK = 'APPROVED_WITH_RISK';
    public const DECISION_REJECTED = 'REJECTED';
    public const DECISION_PENDING = 'PENDING';

    /** @var array<int,string> */
    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_FINANCE,
        self::ROLE_SALES,
        self::ROLE_OPERATIONS,
        self::ROLE_LEGAL_PRIVACY,
        self::ROLE_TECHNICAL,
        self::ROLE_SUPPORT,
    ];

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_APPROVED_WITH_RISK,
        self::DECISION_REJECTED,
        self::DECISION_PENDING,
    ];

    protected $fillable = [
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
}
