<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A preserved public website sign-off record (Sprint 21). A REJECTED signoff
 * forces NO-GO; an APPROVED_WITH_RISK signoff forces WATCH. Records are never
 * deleted and never carry secrets.
 */
class PublicWebsiteSignoff extends Model
{
    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_APPROVED_WITH_RISK = 'APPROVED_WITH_RISK';
    public const DECISION_REJECTED = 'REJECTED';
    public const DECISION_PENDING = 'PENDING';

    public const ROLE_OWNER = 'OWNER';
    public const ROLE_TECHNICAL = 'TECHNICAL';
    public const ROLE_SALES = 'SALES';
    public const ROLE_OPERATIONS = 'OPERATIONS';
    public const ROLE_LEGAL_PRIVACY = 'LEGAL_PRIVACY';

    /** @var array<int,string> */
    public const DECISIONS = [
        self::DECISION_APPROVED,
        self::DECISION_APPROVED_WITH_RISK,
        self::DECISION_REJECTED,
        self::DECISION_PENDING,
    ];

    /** @var array<int,string> */
    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_TECHNICAL,
        self::ROLE_SALES,
        self::ROLE_OPERATIONS,
        self::ROLE_LEGAL_PRIVACY,
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

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
