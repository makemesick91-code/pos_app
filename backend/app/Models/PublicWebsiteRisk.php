<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public website risk (Sprint 21). A tracked risk with severity, status, area,
 * owner, mitigation and accepted-risk governance. Open CRITICAL/HIGH without a
 * valid accepted risk forces NO-GO; open MEDIUM forces WATCH. No secrets or
 * private customer data are stored.
 */
class PublicWebsiteRisk extends Model
{
    public const SEVERITY_CRITICAL = 'CRITICAL';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_INFO = 'INFO';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_MITIGATED = 'MITIGATED';
    public const STATUS_ACCEPTED_RISK = 'ACCEPTED_RISK';
    public const STATUS_CLOSED = 'CLOSED';

    public const AREA_CONTENT_CLAIM = 'CONTENT_CLAIM';
    public const AREA_PRICING_ALIGNMENT = 'PRICING_ALIGNMENT';
    public const AREA_PRIVACY = 'PRIVACY';
    public const AREA_LEGAL_TERMS = 'LEGAL_TERMS';
    public const AREA_LEAD_CAPTURE = 'LEAD_CAPTURE';
    public const AREA_SEO = 'SEO';
    public const AREA_PERFORMANCE = 'PERFORMANCE';
    public const AREA_ACCESSIBILITY = 'ACCESSIBILITY';
    public const AREA_SECURITY = 'SECURITY';
    public const AREA_OPERATIONS = 'OPERATIONS';
    public const AREA_OTHER = 'OTHER';

    /** @var array<int,string> */
    public const SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_HIGH,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_LOW,
        self::SEVERITY_INFO,
    ];

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_MITIGATED,
        self::STATUS_ACCEPTED_RISK,
        self::STATUS_CLOSED,
    ];

    /** @var array<int,string> */
    public const AREAS = [
        self::AREA_CONTENT_CLAIM,
        self::AREA_PRICING_ALIGNMENT,
        self::AREA_PRIVACY,
        self::AREA_LEGAL_TERMS,
        self::AREA_LEAD_CAPTURE,
        self::AREA_SEO,
        self::AREA_PERFORMANCE,
        self::AREA_ACCESSIBILITY,
        self::AREA_SECURITY,
        self::AREA_OPERATIONS,
        self::AREA_OTHER,
    ];

    protected $fillable = [
        'risk_reference',
        'area',
        'severity',
        'status',
        'title',
        'description',
        'owner_user_id',
        'mitigation',
        'accepted_risk_at',
        'accepted_risk_by',
        'accepted_risk_reason',
        'accepted_risk_expires_at',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accepted_risk_at' => 'datetime',
            'accepted_risk_expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function acceptedRiskBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_risk_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
