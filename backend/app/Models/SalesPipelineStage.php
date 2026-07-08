<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales pipeline stage (Sprint 22). Canonical, ordered pipeline stage.
 * Governance metadata only — a stage NEVER creates a tenant/user/subscription/
 * device and never bills. No secrets are stored.
 */
class SalesPipelineStage extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const CODE_NEW = 'NEW';
    public const CODE_CONTACTED = 'CONTACTED';
    public const CODE_QUALIFIED = 'QUALIFIED';
    public const CODE_DEMO_SCHEDULED = 'DEMO_SCHEDULED';
    public const CODE_PROPOSAL_SENT = 'PROPOSAL_SENT';
    public const CODE_NEGOTIATION = 'NEGOTIATION';
    public const CODE_WON_READY_FOR_ONBOARDING = 'WON_READY_FOR_ONBOARDING';
    public const CODE_LOST = 'LOST';
    public const CODE_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    /** @var array<int,string> Terminal stage codes. */
    public const TERMINAL_CODES = [
        self::CODE_WON_READY_FOR_ONBOARDING,
        self::CODE_LOST,
        self::CODE_ARCHIVED,
    ];

    protected $fillable = [
        'stage_code',
        'name',
        'description',
        'sort_order',
        'status',
        'is_default',
        'is_terminal',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_terminal' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(SalesLead::class, 'pipeline_stage_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
