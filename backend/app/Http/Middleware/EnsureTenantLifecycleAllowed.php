<?php

namespace App\Http\Middleware;

use App\Services\TenantLifecycle\TenantLifecycleAccessGuard;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 25 — blocks operational (POS) APIs when the tenant's lifecycle is not
 * allowed, most importantly when a platform admin has manually suspended the
 * tenant (TLS-R003). Runs after auth:sanctum, tenant.active, tenant.context, and
 * subscription.active.
 *
 * The allowed/blocked decision is always recomputed server-side by
 * TenantLifecycleAccessGuard from the manual-suspension source of truth — never
 * trusted from the client, which is UX only (TLS-R009). Manual suspension has
 * precedence over renewal/dunning automation (TLS-R004). saas_admin requests
 * carry no tenant and pass through so platform admins can still govern tenants
 * (TLS-R008). Auth/logout/me, subscription status, tenant-context, health, and
 * device registration routes are an explicit allowlist and are NOT wrapped by
 * this middleware (see routes/api.php and config/tenant_lifecycle.php).
 */
class EnsureTenantLifecycleAllowed
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantLifecycleAccessGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        // Platform admins operate without tenant context.
        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->guard->decide($tenant);

        if ($decision->blocked()) {
            return response()->json([
                'message' => 'Tenant access is suspended.',
                'code' => $decision->code ?? 'TENANT_SUSPENDED',
                'tenant_status' => $decision->status,
            ], Response::HTTP_LOCKED);
        }

        return $next($request);
    }
}
