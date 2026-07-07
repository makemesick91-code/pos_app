<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 11 — guards the admin SaaS control panel APIs (/api/v1/admin/*).
 *
 * Runs after auth:sanctum. The authenticated user must carry the backend
 * platform-admin flag; tenant business users (owners, cashiers, etc.) are
 * always blocked with a stable 403 payload. Authorization is entirely
 * backend-enforced — no client input can grant admin access.
 *
 * - 401 when unauthenticated (defence in depth; auth:sanctum runs first).
 * - 403 with PLATFORM_ADMIN_REQUIRED when the user is not a platform admin.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active || ! $user->isPlatformAdmin()) {
            return response()->json([
                'message' => 'Platform admin access required',
                'code' => 'PLATFORM_ADMIN_REQUIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
