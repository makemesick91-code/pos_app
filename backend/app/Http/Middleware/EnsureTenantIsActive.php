<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees that the authenticated user, and the tenant/store they are bound
 * to, are all active before a tenant route runs.
 *
 * - 401 when unauthenticated.
 * - 403 when the user is disabled, or their tenant is not active, or their
 *   assigned store is not active.
 *
 * saas_admin users carry no tenant and are allowed through for platform routes.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'User account is not active.'], 403);
        }

        // Platform admins do not require tenant context.
        if ($user->isSaasAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant === null || $tenant->status !== Tenant::STATUS_ACTIVE) {
            return response()->json(['message' => 'Tenant is not active.'], 403);
        }

        if ($user->store_id !== null) {
            $store = $user->store;

            if ($store === null || ! $store->isActive()) {
                return response()->json(['message' => 'Store is not active.'], 403);
            }
        }

        return $next($request);
    }
}
