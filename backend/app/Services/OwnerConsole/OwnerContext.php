<?php

namespace App\Services\OwnerConsole;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantLifecycleDecision;

/**
 * UIX-4 — the server-resolved tenant context for a Tenant Owner Web Console
 * request. Immutable, and only ever produced by {@see OwnerContextResolver}
 * from the authenticated owner's own record — never from request input
 * (UIX4-R004/R005).
 *
 * Carries the authoritative lifecycle decision ({@see TenantLifecycleService})
 * so pages can present a truthful status and restrict business data when the
 * tenant is not operational, without recomputing lifecycle state
 * (UIX4-R009/R011).
 */
final class OwnerContext
{
    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly TenantLifecycleDecision $lifecycle,
    ) {}

    public function tenantId(): int
    {
        return (int) $this->tenant->id;
    }

    /**
     * Whether the tenant may see its business data. When false (suspended /
     * archived / past-due-blocked) business pages degrade to a truthful status
     * view rather than exposing operational data.
     */
    public function operational(): bool
    {
        return $this->lifecycle->allowed;
    }
}
