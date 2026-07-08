<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SaaS billing cycle (Sprint 23). A governance period grouping platform-to-
 * tenant invoices. Transitions are conservative. No secrets stored.
 */
class SaasBillingCycle extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_LOCKED = 'LOCKED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_LOCKED,
        self::STATUS_CLOSED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'cycle_reference',
        'period_start',
        'period_end',
        'status',
        'billing_month',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SaasBillingInvoice::class, 'billing_cycle_id');
    }
}
