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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function registeredDevices(): HasMany
    {
        return $this->hasMany(RegisteredDevice::class);
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class);
    }

    public function onboardingRuns(): HasMany
    {
        return $this->hasMany(TenantOnboardingRun::class);
    }

    /**
     * The tenant's most recent subscription row. The authoritative allowed/
     * blocked decision is computed by SubscriptionStatusService — this is only
     * the current record it operates on.
     */
    public function currentSubscription(): ?TenantSubscription
    {
        return $this->tenantSubscriptions()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, RegisteredDevice>
     */
    public function activeRegisteredDevices()
    {
        return $this->registeredDevices()
            ->where('status', RegisteredDevice::STATUS_ACTIVE)
            ->get();
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
