<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A governed public website page (Sprint 21). Content is governance metadata
 * only. Page content must never contain secrets, must never expose internal
 * admin URLs, and any package/pricing content must be aligned with the commercial
 * package catalog.
 */
class PublicWebsitePage extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEW = 'REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const KEY_HOME = 'HOME';
    public const KEY_PACKAGES = 'PACKAGES';
    public const KEY_PRIVACY = 'PRIVACY';
    public const KEY_TERMS = 'TERMS';
    public const KEY_THANK_YOU = 'THANK_YOU';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
        self::STATUS_BLOCKED,
    ];

    /** @var array<int,string> */
    public const KEYS = [
        self::KEY_HOME,
        self::KEY_PACKAGES,
        self::KEY_PRIVACY,
        self::KEY_TERMS,
        self::KEY_THANK_YOU,
    ];

    protected $fillable = [
        'page_key',
        'slug',
        'title',
        'status',
        'seo_title',
        'seo_description',
        'content_sections',
        'published_at',
        'approved_by',
        'approved_at',
        'evidence_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'content_sections' => 'array',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
