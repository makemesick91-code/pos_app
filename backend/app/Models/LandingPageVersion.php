<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A versioned landing page content record (Sprint 21). CTA targets may point to
 * the interest form only, never account creation. Package highlights must align
 * with the commercial package catalog. No secrets, no live tracking tokens.
 */
class LandingPageVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_BLOCKED = 'BLOCKED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
        self::STATUS_BLOCKED,
    ];

    protected $fillable = [
        'version_reference',
        'status',
        'headline',
        'subheadline',
        'hero_cta_label',
        'hero_cta_target',
        'target_segments',
        'package_highlights',
        'feature_highlights',
        'proof_points',
        'faq_items',
        'seo_summary',
        'privacy_summary',
        'evidence_reference',
        'created_by',
        'approved_by',
        'approved_at',
        'published_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_segments' => 'array',
            'package_highlights' => 'array',
            'feature_highlights' => 'array',
            'proof_points' => 'array',
            'faq_items' => 'array',
            'seo_summary' => 'array',
            'privacy_summary' => 'array',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'metadata' => 'array',
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeApprovedOrPublished(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PUBLISHED]);
    }
}
