<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'name',
        'business_type',
        'owner_name',
        'owner_phone',
        'status',
        'subscription_plan',
        'subscription_status',
        'subscription_started_at',
        'subscription_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_started_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productStorePrices(): HasMany
    {
        return $this->hasMany(ProductStorePrice::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
