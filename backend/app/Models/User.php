<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SAAS_ADMIN = 'saas_admin';
    public const ROLE_TENANT_OWNER = 'tenant_owner';
    public const ROLE_STORE_ADMIN = 'store_admin';
    public const ROLE_CASHIER = 'cashier';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'tenant_id',
        'store_id',
        'role',
        'is_active',
        'is_platform_admin',
        'platform_admin_granted_at',
        'platform_admin_revoked_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'platform_admin_granted_at' => 'datetime',
            'platform_admin_revoked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isSaasAdmin(): bool
    {
        return $this->role === self::ROLE_SAAS_ADMIN;
    }

    /**
     * Whether this user is a platform administrator with access to the admin
     * SaaS control panel APIs (Sprint 11). This is a distinct backend-enforced
     * authorization flag — being a platform admin never grants tenant business
     * API access, and tenant business users are never platform admins.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isTenantOwner(): bool
    {
        return $this->role === self::ROLE_TENANT_OWNER;
    }

    public function isStoreAdmin(): bool
    {
        return $this->role === self::ROLE_STORE_ADMIN;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function belongsToTenant(?int $tenantId): bool
    {
        return $tenantId !== null && (int) $this->tenant_id === (int) $tenantId;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_id');
    }

    public function createdInventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'created_by');
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'actor_user_id');
    }

    public function requestedOnboardings(): HasMany
    {
        return $this->hasMany(TenantOnboardingRun::class, 'requested_by');
    }

    public function reportedPilotDefects(): HasMany
    {
        return $this->hasMany(PilotDefect::class, 'reported_by');
    }

    public function assignedPilotDefects(): HasMany
    {
        return $this->hasMany(PilotDefect::class, 'assigned_to');
    }
}
