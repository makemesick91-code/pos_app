<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
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
 * UIX-3 — browser (session/cookie) login for the platform-admin SaaS Control
 * Center. Distinct from the API Sanctum token flow ({@see App\Http\Controllers\Api\V1\AuthController}).
 *
 * Security posture:
 * - One generic failure message for every reason (bad password, unknown email,
 *   deactivated account, or a valid non-platform-admin) — no account enumeration
 *   and no "you are not an admin" disclosure.
 * - A non-admin session is NEVER created: credentials are verified and the
 *   platform-admin predicate checked BEFORE Auth::login(), so a tenant user can
 *   never obtain a console session.
 * - Rate limited per (email, ip) to blunt credential stuffing / brute force.
 * - Session id regenerated on login (fixation) and invalidated on logout.
 * - Passwords are never logged, audited, or echoed. Successful login/logout are
 *   audited via {@see AdminAuditLogger}; failures are logged with a hashed
 *   identifier only.
 */
class AdminLoginController extends Controller
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
        $user = Auth::guard('web')->user();

        if ($user !== null && $user->is_active && $user->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
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
            // Still spend a hash cycle on unknown accounts to normalise timing.
            : Hash::check((string) $request->input('password'), self::TIMING_SAFE_HASH);

        $authorized = $user !== null
            && $passwordOk
            && $user->is_active
            && $user->isPlatformAdmin();

        if (! $authorized) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            // Redacted, non-enumerating failure signal for detection. Never logs
            // the password or the raw email.
            Log::warning('admin.login.failed', [
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
        Auth::guard('web')->login($user, (bool) $request->boolean('remember'));

        $user->forceFill(['last_login_at' => now()])->save();

        $this->auditLogger->log(
            actor: $user,
            action: AdminAuditLog::ACTION_ADMIN_LOGIN,
            targetType: 'User',
            targetId: $user->id,
            metadata: ['channel' => 'web_console'],
            request: $request,
        );

        // intended() only replays a same-origin path stored by the auth
        // middleware — no user-supplied redirect param is honoured (no open
        // redirect).
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if ($user !== null) {
            $this->auditLogger->log(
                actor: $user,
                action: AdminAuditLog::ACTION_ADMIN_LOGOUT,
                targetType: 'User',
                targetId: $user->id,
                metadata: ['channel' => 'web_console'],
                request: $request,
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'admin-login|'.hash('sha256', strtolower($email)).'|'.$request->ip();
    }
}
