<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * UIX-3 — session/web variant of the platform-admin gate for the browser-based
 * SaaS Control Center (/admin/*).
 *
 * Mirrors the authorization predicate of {@see EnsurePlatformAdmin} (is_active
 * AND isPlatformAdmin) but responds for a browser: unauthenticated visitors are
 * redirected to the login page (deny-by-default), and an authenticated session
 * that is NOT an active platform admin is force-logged-out and bounced back to
 * login. Authorization is entirely backend-enforced — no request input, hidden
 * field, or client state can grant admin access, and a tenant business session
 * can never reach a platform route.
 *
 * The response is a redirect (never JSON) and never leaks whether a given
 * account exists or why access was denied.
 */
class EnsurePlatformAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            // Deny-by-default: store the intended URL and send to login.
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->is_active || ! $user->isPlatformAdmin()) {
            // Defence in depth: a non-admin (or deactivated admin) session must
            // never see the console. Invalidate it and bounce to login with a
            // stable, non-enumerating message.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'Platform admin access required.']);
        }

        // Authenticated admin pages must never be cached by shared proxies.
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
