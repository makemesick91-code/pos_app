<?php

namespace App\Services\OwnerConsole;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantLifecycleService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * UIX-4 — resolves the current {@see OwnerContext} for a Tenant Owner Web
 * Console request.
 *
 * The tenant is derived ENTIRELY from the authenticated owner's own
 * `tenant_id` (server-side, single authorized tenant); there is no
 * client-supplied tenant selector, route parameter, or header that can choose
 * or switch it (UIX4-R004/R005/R008). The owner predicate is re-validated here
 * as defence in depth even though {@see EnsureTenantOwnerWeb} already gated the
 * route.
 *
 * The domain today models exactly one tenant per user (a scalar `users.tenant_id`
 * foreign key, no membership pivot), so this resolver intentionally exposes a
 * single-tenant context and no switcher — see
 * docs/architecture/uix-4-tenant-owner-web-console.md.
 */
class OwnerContextResolver
{
    public function __construct(
        private readonly TenantLifecycleService $lifecycle,
    ) {}

    /**
     * Resolve the context or null when the session is not an eligible owner.
     */
    public function resolve(): ?OwnerContext
    {
        $user = Auth::guard('owner')->user();

        if (! $user instanceof User
            || ! $user->is_active
            || ! $user->isTenantOwner()
            || $user->tenant_id === null) {
            return null;
        }

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return new OwnerContext(
            user: $user,
            tenant: $tenant,
            lifecycle: $this->lifecycle->resolve($tenant),
        );
    }

    /**
     * Resolve the context or throw. Used inside routes already guarded by
     * {@see EnsureTenantOwnerWeb}, where a null context indicates a broken
     * invariant rather than an ordinary unauthenticated request.
     */
    public function require(): OwnerContext
    {
        $context = $this->resolve();

        if ($context === null) {
            throw new RuntimeException('Owner context could not be resolved for a guarded owner route.');
        }

        return $context;
    }
}
