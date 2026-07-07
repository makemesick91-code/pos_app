<?php

namespace App\Http\Middleware;

use App\Services\Subscriptions\SubscriptionStatusService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks protected business APIs when the tenant's subscription is not allowed
 * (Sprint 10). Runs after auth:sanctum, tenant.active, and tenant.context.
 *
 * The allowed/blocked decision is always recomputed by
 * SubscriptionStatusService from the subscription date columns — never trusted
 * from the client. saas_admin requests carry no tenant and pass through.
 * Auth/logout/me, subscription status, and device registration routes are NOT
 * wrapped by this middleware (see routes/api.php).
 */
class EnsureTenantSubscriptionIsActive
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SubscriptionStatusService $subscriptionStatus,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        // Platform admins operate without tenant context.
        if ($tenant === null) {
            return $next($request);
        }

        $status = $this->subscriptionStatus->resolve($tenant);

        if (! $status->allowed) {
            return response()->json([
                'message' => 'Subscription inactive',
                'code' => $status->code(),
                'status' => $status->status,
                'reason' => $status->reason,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return $next($request);
    }
}
