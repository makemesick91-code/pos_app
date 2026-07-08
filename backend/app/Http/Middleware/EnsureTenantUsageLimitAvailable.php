<?php

namespace App\Http\Middleware;

use App\Services\TenantPlan\TenantUsageLimitService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 26 — blocks a protected mutation when the tenant has reached its plan
 * usage limit for the given key (TPE-R003, TPE-R009).
 *
 * Runs AFTER tenant.lifecycle and tenant.entitled: lifecycle blocking (TPE-R004)
 * and entitlement denial take precedence, so a suspended tenant returns
 * TENANT_SUSPENDED and an unentitled tenant returns FEATURE_NOT_ENTITLED before a
 * usage check is ever reached. Unlimited plans and limits not configured for the
 * plan pass through. The current usage is computed server-side by
 * TenantUsageLimitService from real DB counts and never trusted from the client.
 * Platform admins carry no tenant and pass through.
 *
 * Usage: ->middleware('tenant.usage.limit:products.max')
 */
class EnsureTenantUsageLimitAvailable
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantUsageLimitService $usage,
    ) {}

    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->usage->canUse($tenant, $limitKey, 1);

        if ($decision->exceeded()) {
            return response()->json([
                'message' => 'Usage limit exceeded for this tenant plan.',
                'code' => $decision->code ?? 'USAGE_LIMIT_EXCEEDED',
                'limit' => $limitKey,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $next($request);
    }
}
