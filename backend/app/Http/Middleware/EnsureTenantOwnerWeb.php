<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-4 — session/web gate for the Tenant Owner Web Console (/owner/*).
 *
 * The predicate is a first-class tenant-owner identity, NEVER a platform
 * capability: the authenticated user on the dedicated `owner` guard must be
 * active, carry the `tenant_owner` role, and resolve to a real tenant
 * (UIX4-R002/R004). It deliberately runs on a separate guard from the
 * platform-admin console (`web`), so a platform-admin session can never reach
 * an owner route and an owner session can never reach the admin console
 * (UIX4-R002/R003) — surface separation is enforced at the session layer, not
 * merely by hiding navigation.
 *
 * Behaviour mirrors {@see EnsurePlatformAdminWeb}: unauthenticated visitors are
 * redirected to the owner login (deny-by-default), and an authenticated session
 * that no longer satisfies the predicate (deactivated, role changed, tenant
 * removed) is force-logged-out and bounced back to login with a stable,
 * non-enumerating message. Authenticated pages are marked non-cacheable.
 *
 * The tenant is ALWAYS derived server-side from the owner's own record; no
 * request input, route parameter, header, or cookie can select or switch it
 * (UIX4-R004/R005).
 */
class EnsureTenantOwnerWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('owner')->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('owner.login'));
        }

        if (! $this->isEligibleOwner($user)) {
            Auth::guard('owner')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('owner.login')
                ->withErrors(['email' => 'Tenant owner access required.']);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * The owner predicate: active + tenant_owner role + a resolvable tenant.
     * A platform admin (is_platform_admin, tenant_id null) never satisfies it.
     */
    private function isEligibleOwner(User $user): bool
    {
        return $user->is_active
            && $user->isTenantOwner()
            && $user->tenant_id !== null
            && $user->tenant instanceof Tenant;
    }
}
