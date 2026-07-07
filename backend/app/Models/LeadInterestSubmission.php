<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An interest-only public lead submission (Sprint 21). A lead NEVER creates a
 * tenant, user, subscription, or device and NEVER triggers a real email/WhatsApp.
 * Consent is required. Follow-up is a manual, human process.
 */
class LeadInterestSubmission extends Model
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_REVIEWED = 'REVIEWED';
    public const STATUS_CONTACTED = 'CONTACTED';
    public const STATUS_QUALIFIED = 'QUALIFIED';
    public const STATUS_DISQUALIFIED = 'DISQUALIFIED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS_SPAM = 'SPAM';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_REVIEWED,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_DISQUALIFIED,
        self::STATUS_ARCHIVED,
        self::STATUS_SPAM,
    ];

    protected $fillable = [
        'lead_reference',
        'status',
        'business_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'business_type',
        'estimated_store_count',
        'estimated_device_count',
        'interest_package_code',
        'message',
        'source',
        'consent_accepted_at',
        'processed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'estimated_store_count' => 'integer',
            'estimated_device_count' => 'integer',
            'consent_accepted_at' => 'datetime',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
