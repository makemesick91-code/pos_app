<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS package catalog entry (Sprint 20). Internal/admin-only commercial
 * package definition: code, target segment, feature boundaries, device/store/user
 * limits, onboarding level, support level, status and pricing metadata. Pricing is
 * governance metadata only — it activates no real billing and never bypasses the
 * SubscriptionPlan/TenantSubscription/RegisteredDevice runtime enforcement.
 */
class SaasPackageCatalog extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_RETIRED = 'RETIRED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const SEGMENT_WARUNG = 'WARUNG';
    public const SEGMENT_TOKO_KECIL = 'TOKO_KECIL';
    public const SEGMENT_KEDAI = 'KEDAI';
    public const SEGMENT_LAUNDRY = 'LAUNDRY';
    public const SEGMENT_RETAIL = 'RETAIL';
    public const SEGMENT_APOTEK_LIGHT = 'APOTEK_LIGHT';
    public const SEGMENT_GENERAL_UMKM = 'GENERAL_UMKM';

    public const ONBOARDING_SELF_GUIDED = 'SELF_GUIDED';
    public const ONBOARDING_ASSISTED = 'ASSISTED';
    public const ONBOARDING_MANAGED = 'MANAGED';

    public const SUPPORT_BASIC = 'BASIC';
    public const SUPPORT_STANDARD = 'STANDARD';
    public const SUPPORT_PRIORITY = 'PRIORITY';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_ACTIVE,
        self::STATUS_RETIRED,
        self::STATUS_BLOCKED,
    ];

    /** @var array<int,string> */
    public const SEGMENTS = [
        self::SEGMENT_WARUNG,
        self::SEGMENT_TOKO_KECIL,
        self::SEGMENT_KEDAI,
        self::SEGMENT_LAUNDRY,
        self::SEGMENT_RETAIL,
        self::SEGMENT_APOTEK_LIGHT,
        self::SEGMENT_GENERAL_UMKM,
    ];

    /** @var array<int,string> */
    public const ONBOARDING_LEVELS = [
        self::ONBOARDING_SELF_GUIDED,
        self::ONBOARDING_ASSISTED,
        self::ONBOARDING_MANAGED,
    ];

    /** @var array<int,string> */
    public const SUPPORT_LEVELS = [
        self::SUPPORT_BASIC,
        self::SUPPORT_STANDARD,
        self::SUPPORT_PRIORITY,
    ];

    protected $fillable = [
        'package_code',
        'name',
        'target_segment',
        'status',
        'monthly_price',
        'currency',
        'device_limit',
        'store_limit',
        'user_limit',
        'onboarding_level',
        'support_level',
        'feature_flags',
        'included_modules',
        'excluded_modules',
        'commercial_notes',
        'evidence_reference',
        'metadata',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'device_limit' => 'integer',
            'store_limit' => 'integer',
            'user_limit' => 'integer',
            'feature_flags' => 'array',
            'included_modules' => 'array',
            'excluded_modules' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
