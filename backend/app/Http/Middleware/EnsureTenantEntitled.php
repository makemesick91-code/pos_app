<?php

namespace App\Http\Middleware;

use App\Services\TenantPlan\TenantEntitlementGuard;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 26 — blocks an operational route when the tenant's plan (with any active
 * override applied) does not entitle the required feature (TPE-R002, TPE-R008).
 *
 * Runs AFTER auth:sanctum, tenant.active, tenant.context, subscription.active, and
 * — crucially — tenant.lifecycle (TPE-R004). A suspended/cancelled/archived tenant
 * is therefore already blocked (423 TENANT_SUSPENDED) before this guard ever runs,
 * so an entitlement/plan can never re-enable a suspended tenant (TPE-R005). The
 * entitled/denied answer is always recomputed server-side by TenantEntitlementGuard
 * and never trusted from the client. Platform admins carry no tenant and pass
 * through.
 *
 * Usage: ->middleware('tenant.entitled:pos.sales')
 */
class EnsureTenantEntitled
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantEntitlementGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->guard->decide($tenant, $feature);

        if ($decision->denied()) {
            return response()->json([
                'message' => 'This feature is not available on the tenant plan.',
                'code' => $decision->code ?? 'FEATURE_NOT_ENTITLED',
                'feature' => $feature,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
