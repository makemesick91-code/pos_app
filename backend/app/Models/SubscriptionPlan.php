<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A backend-owned subscription plan (Sprint 10). Plans cap a tenant's stores,
 * devices, and (optionally) products. They are seeded/managed by the platform
 * and never created from client input. See Sprint 10 evidence.
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    public const CODE_LITE = 'lite';
    public const CODE_STARTER = 'starter';
    public const CODE_PRO = 'pro';

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly',
        'max_stores',
        'max_devices',
        'max_products',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'max_stores' => 'integer',
            'max_devices' => 'integer',
            'max_products' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
