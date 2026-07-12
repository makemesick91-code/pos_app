# UIX-3 — Platform Admin Control Center Threat Model

Scope: the browser (session/cookie) platform-admin surface introduced in UIX-3
(`/admin/*`), its login flow, and secure admin provisioning. The console is
read-only (no tenant mutation routes), so the threat surface is dominated by
authentication, authorization, session integrity, and information disclosure.

**Public plaintext HTTP admin access = NO-GO.** While no HTTPS/domain is
available for the pilot, this surface must be reached ONLY over an encrypted
operator channel (SSH tunnel / VPN / private network). Exposing the admin login
or console over public plaintext HTTP is a NO-GO condition regardless of the
application-layer controls below, because credentials and the session cookie
would traverse the network in the clear.

## Threat table

| # | Threat | Preventive control | Detective control | Test | Runtime verification |
| --- | --- | --- | --- | --- | --- |
| 1 | Credential stuffing | Per-`(email, ip)` rate limit via `RateLimiter` (5 attempts / 60s decay) in `AdminLoginController::login`; generic single failure message | `Log::warning('admin.login.failed', {email_hash, ip})` on every failed attempt | `Uix3AdminAuthTest` (rate-limit / lockout cases) | Trigger >5 failed logins from one source; confirm 429/throttle message and `admin.login.failed` log entries with hashed email only |
| 2 | Brute force (single account) | Same per-`(email,ip)` throttle; bcrypt verification cost; no unlimited attempts | Failed-login warning log with `email_hash` + ip | `Uix3AdminAuthTest` | Observe throttle engages; confirm no plaintext email/password in logs |
| 3 | Account enumeration | One generic failure message for every reason (bad password, unknown email, deactivated, valid-but-not-admin); timing normalized by running a bcrypt check against a constant `TIMING_SAFE_HASH` on unknown emails | Uniform failure log shape (hashed identifier only) | `Uix3AdminAuthTest`, `Uix3AdminSecurityTest` | Compare responses/timing for known vs unknown email — identical message, comparable timing |
| 4 | Session fixation | `session()->regenerate()` immediately before `Auth::login` on successful login | Login audit `ACTION_ADMIN_LOGIN` | `Uix3AdminAuthTest` | Capture pre-login session id, log in, confirm session id rotated |
| 5 | CSRF | Web middleware group enforces CSRF token on all POST (`login`, `logout`); logout is POST-only | Framework 419 on token mismatch | `Uix3AdminSecurityTest` / `Uix3AdminAuthTest` | POST without token -> 419; confirm logout rejects GET |
| 6 | Open redirect | Only `redirect()->intended(admin.dashboard)` is used — replays a same-origin path stored by auth middleware; no user-supplied redirect param is honoured | n/a (no redirect param accepted) | `Uix3AdminSecurityTest` | Attempt `?redirect=`/`?next=` to external host; confirm ignored, lands on dashboard |
| 7 | Privilege escalation via request input | Authorization is entirely backend-enforced (`is_active && isPlatformAdmin`); no hidden field, request param, or client state can grant admin | Middleware force-logout of non-admin session | `Uix3AdminSecurityTest`, `Uix3ControlCenterTest` | Craft requests with forged role fields; confirm no effect |
| 8 | Tenant user -> platform admin session | Credentials AND platform-admin predicate checked BEFORE `Auth::login`, so a non-admin session is never created; middleware also force-logs-out any authenticated non-admin session and invalidates it | `admin.login.failed` warning; middleware logout event | `Uix3AdminAuthTest`, `Uix3AdminSecurityTest` | Log in as a valid tenant (non-admin) user -> denied, no console session, generic message |
| 9 | IDOR (tenant detail) | `{tenant}` resolved via route-model binding; platform admin is authorized cross-tenant by role; whitelisted sort/status/per_page params prevent query injection | `ACTION_TENANT_VIEWED` audit per detail view | `Uix3ControlCenterTest` | View several tenants; confirm each view is audited and only authorized admins reach it |
| 10 | Cross-tenant data leakage | Detail data comes from `SupportTenantHealthService::overview` which is already redacted; dashboard groups come from canonical summary services with their own domain redactors; no raw tenant secrets rendered | Redaction enforced in source services | `Uix3ControlCenterTest` | Inspect rendered detail/dashboard; confirm no secrets/tokens/credentials present |
| 11 | Sensitive audit / logging exposure | Passwords never logged, audited, or echoed; failed logins log `sha256(email)` only; `AdminAuditLogger` sanitizes metadata; provision command logs `email_hash` only | Audit trail (`AdminAuditLog`) for login/logout/tenant-view | `Uix3AdminAuthTest`, `Uix3PlatformAdminProvisionCommandTest` | Grep logs after auth events; confirm no plaintext password/email, only hashes |
| 12 | Cache leakage (shared proxy) | `EnsurePlatformAdminWeb` sets `Cache-Control: no-store, no-cache, must-revalidate, private` and `Pragma: no-cache` on every authenticated response; `noindex, nofollow` + `referrer: same-origin` meta | Response header assertions | `Uix3AdminSecurityTest` | `curl -I` an authenticated page (over the encrypted channel); confirm `no-store, private` headers present |
| 13 | Plaintext HTTP interception | Operator-channel-only access (SSH tunnel / VPN / private network) while no HTTPS/domain; nginx site is IP-restricted on port 8080; `noindex/nofollow` | Access restricted at nginx (IP allowlist) | Runbook smoke (over channel) | Confirm public plaintext admin is unreachable; access only via the encrypted channel. **Public plaintext HTTP = NO-GO** |
| 14 | Unsafe provisioning | `php artisan platform:admin-provision`: password via hidden `secret()` prompt or one STDIN line (`--stdin-password`) — NEVER a visible CLI arg; strength enforced (>=12 chars, letter+digit, not in forbidden-common list, must not contain account name); hashed; idempotent; NO seeded default admin | Redacted `platform.admin.provisioned` info log (`email_hash`, `created`, `password_rotated`) | `Uix3PlatformAdminProvisionCommandTest` | Provision an admin over the operator channel; confirm password never appears in shell history / process list / logs |

## Notes

- The console is read-only: there are no tenant mutation routes, which removes an
  entire class of state-changing abuse from the threat surface.
- Authorization predicate `is_active && isPlatformAdmin` is enforced in two
  places — before `Auth::login` (so no non-admin session is ever created) and in
  `EnsurePlatformAdminWeb` on every request (defence in depth).
- Middleware responds with redirects, never JSON, and never discloses whether an
  account exists or why access was denied.
