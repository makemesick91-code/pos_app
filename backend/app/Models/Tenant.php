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

    public function dataImportRuns(): HasMany
    {
        return $this->hasMany(TenantDataImportRun::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(TenantSupplier::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(TenantCustomer::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(TenantPaymentMethod::class);
    }

    public function defaultSettings(): HasMany
    {
        return $this->hasMany(TenantDefaultSetting::class);
    }

    public function tenantSubscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function pilotDefects(): HasMany
    {
        return $this->hasMany(PilotDefect::class);
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

    public function manualSuspensions(): HasMany
    {
        return $this->hasMany(TenantManualSuspension::class);
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(TenantLifecycleEvent::class);
    }

    public function planAssignments(): HasMany
    {
        return $this->hasMany(TenantPlanAssignment::class);
    }

    public function entitlementOverrides(): HasMany
    {
        return $this->hasMany(TenantEntitlementOverride::class);
    }

    /**
     * The tenant's currently ACTIVE plan assignment (within its effective
     * window), if any. This is the authoritative plan signal read by
     * TenantPlanResolver (Sprint 26). No plan assignment falls back to the safe
     * default plan resolved from config/tenant_plan.php.
     */
    public function activePlanAssignment(): ?TenantPlanAssignment
    {
        $now = now();

        return $this->planAssignments()
            ->where('status', TenantPlanAssignment::STATUS_ACTIVE)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $now);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TenantEntitlementOverride>
     */
    public function activeEntitlementOverrides()
    {
        $now = now();

        return $this->entitlementOverrides()
            ->where('status', TenantEntitlementOverride::STATUS_ACTIVE)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $now);
            })
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The tenant's currently ACTIVE manual suspension, if any. This is the
     * authoritative manual-suspension signal read by TenantLifecycleService.
     */
    public function activeManualSuspension(): ?TenantManualSuspension
    {
        return $this->manualSuspensions()
            ->where('status', TenantManualSuspension::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();
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
