<?php

namespace App\Http\Middleware;

use App\Services\Entitlements\EntitlementAccessService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 32 — enforces premium feature entitlement at runtime and audits the
 * denial (ENT-R009/R018). Complements the Sprint 26 tenant.entitled guard by
 * persisting a denied entitlement decision through EntitlementAccessService so a
 * blocked premium action is always visible in the entitlement decision log.
 *
 * Usage: ->middleware('entitlement.feature:reports.advanced')
 */
class EnsureFeatureEntitled
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->access->canUseFeature($tenant, $featureKey, $this->context->user());

        if ($decision->denied()) {
            return response()->json([
                'message' => $decision->message,
                'code' => $decision->reasonCode,
                'feature' => $featureKey,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
