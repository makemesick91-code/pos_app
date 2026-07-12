<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerLoginRequest;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * UIX-4 — browser (session/cookie) login for the Tenant Owner Web Console.
 * Runs on the dedicated `owner` guard, fully separate from the platform-admin
 * `web` guard and from the Android/API Sanctum token flow.
 *
 * Security posture (mirrors the platform-admin console, UIX4-R013):
 * - One generic failure message for every reason (bad password, unknown email,
 *   deactivated account, non-owner, or an owner without a tenant) — no account
 *   enumeration and no "you are not an owner" disclosure.
 * - A non-owner session is NEVER created: credentials are verified and the
 *   tenant-owner predicate checked BEFORE Auth::login(), so a platform admin or
 *   cashier can never obtain an owner console session.
 * - Rate limited per (email, ip) to blunt credential stuffing / brute force.
 * - Session id regenerated on login (fixation) and invalidated on logout.
 * - Passwords are never logged, audited, or echoed; successful login/logout are
 *   audited via {@see AdminAuditLogger}; failures log a hashed identifier only.
 */
class OwnerLoginController extends Controller
{
    /**
     * A constant-shape bcrypt hash used to normalise timing when the email is
     * unknown, so response time does not reveal whether an account exists.
     */
    private const TIMING_SAFE_HASH = '$2y$12$oaK9X9JZO31ZhUj56N5dvubXjJZFhjkm5qCWjIB2CF9vq9SoWg.uy';

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly AdminAuditLogger $auditLogger) {}

    public function showLogin(): View|RedirectResponse
    {
        if ($this->isEligibleOwner(Auth::guard('owner')->user())) {
            return redirect()->route('owner.dashboard');
        }

        return view('owner.login');
    }

    public function login(OwnerLoginRequest $request): RedirectResponse
    {
        $email = (string) $request->input('email');
        $throttleKey = $this->throttleKey($email, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', ['seconds' => $seconds])],
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        $passwordOk = $user !== null
            ? Hash::check((string) $request->input('password'), (string) $user->password)
            : Hash::check((string) $request->input('password'), self::TIMING_SAFE_HASH);

        $authorized = $passwordOk && $this->isEligibleOwner($user);

        if (! $authorized) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            Log::warning('owner.login.failed', [
                'email_hash' => hash('sha256', strtolower($email)),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Fixation defence: rotate the session id, then establish the session.
        $request->session()->regenerate();
        Auth::guard('owner')->login($user, (bool) $request->boolean('remember'));

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->log(
            actor: $user,
            action: AdminAuditLog::ACTION_OWNER_LOGIN,
            targetType: AdminAuditLog::TARGET_OWNER_CONSOLE,
            targetId: $user->id,
            tenantId: $user->tenant_id,
            metadata: ['channel' => 'owner_console'],
            request: $request,
        );

        // intended() only replays a same-origin path stored by the auth
        // middleware — no user-supplied redirect param is honoured (no open
        // redirect).
        return redirect()->intended(route('owner.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('owner')->user();

        if ($user instanceof User) {
            $this->auditLogger->log(
                actor: $user,
                action: AdminAuditLog::ACTION_OWNER_LOGOUT,
                targetType: AdminAuditLog::TARGET_OWNER_CONSOLE,
                targetId: $user->id,
                tenantId: $user->tenant_id,
                metadata: ['channel' => 'owner_console'],
                request: $request,
            );
        }

        Auth::guard('owner')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('owner.login');
    }

    /**
     * The owner predicate: active + tenant_owner role + a resolvable tenant.
     */
    private function isEligibleOwner(?User $user): bool
    {
        return $user !== null
            && $user->is_active
            && $user->isTenantOwner()
            && $user->tenant_id !== null
            && $user->tenant !== null;
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'owner-login|'.hash('sha256', strtolower($email)).'|'.$request->ip();
    }
}
