# 30 — Authentication, Session & Security

Auth, session handling, and the security baseline for all four surfaces.

## No production default credentials
- There is **no production default** admin, tenant, or service credential. No seeded,
  hardcoded, or documented default password may exist in any environment reachable in
  production. Bootstrap credentials, if any, are for disposable local/test use only and
  must be rotated out before deploy.
- Secrets (app key, DB password, gateway keys, tokens) come from environment/config only,
  never committed to the repo and never printed in logs, responses, or Blade.

## Authentication per surface
- Android / tenant API and Platform Admin API: Sanctum tokens. Admin routes additionally
  require `platform.admin` via `EnsurePlatformAdmin`.
- Platform Admin browser console (`/admin/*`): session/cookie auth guarded by
  `EnsurePlatformAdminWeb` + `platform.admin.web`. Every admin route is behind that gate;
  no public admin page exists.
- Device activation tokens are sha256-hashed; raw tokens are never stored or returned.

## Session & CSRF
- The `/admin/*` console uses server sessions with CSRF protection on state-changing
  requests, secure/httponly session cookies, and short idle expiry.
- Admin sessions must not be constructible from tenant credentials; the platform-admin
  predicate (`is_platform_admin` AND `is_active`) is re-checked on every request.

## Transport security
- No HTTPS/domain is provisioned yet. Serving the admin console over public plaintext is
  NO-GO. The console is reachable only over an encrypted operator channel (SSH tunnel/VPN)
  bound to port 8080 with IP restriction. Do not remove those restrictions to "make it easy".

## Secret hygiene
- Never expose passwords, secrets, tokens, or raw payment data. Redact via
  `App\Services\Admin\AdminAuditLogger::sanitize()` before any log/audit write.
- Deactivating a user (`is_active` = false) must immediately revoke effective access.
