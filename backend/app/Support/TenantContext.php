<?php

namespace App\Support;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;

/**
 * Per-request tenant context. Bound as a scoped singleton in the container and
 * hydrated by the SetTenantContext middleware from the authenticated user.
 *
 * Tenant/store context is ALWAYS derived from the authenticated user (never
 * arbitrary client input). A client-provided X-Store-ID selector is only
 * honoured after it is validated against the user's own tenant.
 */
class TenantContext
{
    protected ?User $user = null;

    protected ?Tenant $tenant = null;

    protected ?Store $store = null;

    public function set(?User $user, ?Tenant $tenant, ?Store $store): void
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->store = $store;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function store(): ?Store
    {
        return $this->store;
    }

    public function storeId(): ?int
    {
        return $this->store?->id;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId(),
            'store_id' => $this->storeId(),
            'role' => $this->user?->role,
            'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
        ];
    }
}
