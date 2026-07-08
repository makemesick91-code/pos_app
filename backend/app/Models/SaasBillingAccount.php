<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SaaS billing account (Sprint 23). Platform-to-tenant billing governance
 * record. May reference a tenant, but is NEVER a POS cashier/customer payment
 * record. A status change NEVER auto-suspends tenant access. No secrets stored.
 */
class SaasBillingAccount extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ON_HOLD = 'ON_HOLD';
    public const STATUS_SUSPENDED_MANUAL_REVIEW = 'SUSPENDED_MANUAL_REVIEW';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /** @var array<int,string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_SUSPENDED_MANUAL_REVIEW,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'tenant_id',
        'account_reference',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'tax_identifier',
        'status',
        'billing_currency',
        'payment_terms_days',
        'collection_owner_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collectionOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collection_owner_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SaasBillingInvoice::class, 'billing_account_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
