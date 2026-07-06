<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hydrates the per-request TenantContext from the authenticated user.
 *
 * Tenant is always the user's own tenant. The effective store defaults to the
 * user's assigned store. An optional X-Store-ID header may override the store
 * ONLY when the selected store belongs to the user's tenant and is active —
 * otherwise the request is rejected with 403. This is the core tenant-isolation
 * enforcement point: a user can never select a store from another tenant.
 *
 * Must run after auth:sanctum and EnsureTenantIsActive.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // Platform admins operate without tenant context on tenant routes.
        if ($user->isSaasAdmin()) {
            $context->set($user, null, null);

            return $next($request);
        }

        $tenant = $user->tenant;
        $store = $user->store; // default: the user's own store (may be null)

        if ($request->hasHeader('X-Store-ID')) {
            $requestedStoreId = (int) $request->header('X-Store-ID');

            $selected = Store::query()
                ->whereKey($requestedStoreId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($selected === null || ! $selected->isActive()) {
                return response()->json([
                    'message' => 'Invalid store selection for this tenant.',
                ], 403);
            }

            $store = $selected;
        }

        $context->set($user, $tenant, $store);

        return $next($request);
    }
}
