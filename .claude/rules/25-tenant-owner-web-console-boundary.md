# 25 — Tenant Owner Web Console Boundary (UIX-4)

The `/owner/*` browser console is the tenant owner's window into their own
business. It is a distinct surface — not the Platform Admin Console with a new
title.

## Surface & identity
- Owner console lives at `/owner/*`, on the dedicated `owner` session guard,
  gated by `EnsureTenantOwnerWeb` (alias `tenant.owner.web`).
- Owner identity = `users.is_active` AND `role = tenant_owner`
  (`User::ROLE_TENANT_OWNER`) AND a resolvable `tenant_id`. This is NEVER a
  platform capability and never a grantable platform flag (UIX4-R002/R003).
- The `owner` guard and the platform-admin `web` guard are independent: a
  platform-admin session can never reach `/owner/*`, and an owner session can
  never reach `/admin/*`. A Sanctum API token does not authenticate the web
  console.

## Tenant context
- The tenant is resolved server-side from the authenticated owner's own record
  via `App\Services\OwnerConsole\OwnerContextResolver` (UIX4-R004). No route
  parameter, query string, header, cookie, or hidden field may select or switch
  it (UIX4-R005).
- There is no automatic tenant global scope in this codebase, so every owner
  query is explicitly constrained to the owner's `tenant_id`, deny-by-default
  (UIX4-R006). Outlet/device lookups resolve only within the tenant; a foreign
  or unknown id returns 404 (UIX4-R007).
- The domain models one tenant per user (scalar `users.tenant_id`, no membership
  pivot). UIX-4 exposes a single-authorized-tenant context and no switcher. Do
  not fabricate multi-membership; adding it requires a new pivot and a
  `TenantContext` refactor (UIX4-R008).

## Read-only, truthful, source-of-truth
- The console is read-only first (UIX4-R011). No tenant business mutation route
  exists. `App\Services\OwnerConsole\OwnerConsoleReadService` orchestrates reads
  from the canonical services (`TenantLifecycleService`, `TenantPlanResolver`,
  entitlement/usage, billing/onboarding/android-runtime viewers,
  `DailySalesReportService`) and never recomputes business state (UIX4-R009).
- Values are truthful: an unavailable read renders "Tidak tersedia", never a
  fabricated zero (UIX4-R010). A suspended/archived tenant sees its authoritative
  status and billing but not business listings.
- Sensitive device material (activation token hash, device fingerprint hash) is
  never rendered; audit reuses `AdminAuditLogger` sanitization (UIX4-R016).

## Security & transport
- Login: generic failure message, per-(email,ip) throttle, timing
  normalization, session regeneration, POST-only secure logout, no open redirect,
  CSRF on state-changing requests (UIX4-R013). Authenticated pages are
  non-cacheable (UIX4-R014).
- No production default owner credentials; owners are provisioned only via
  `tenant:owner-provision` (hidden password prompt / STDIN, strength-validated,
  hashed) (UIX4-R012).
- While HTTPS/domain is absent the console is reachable only via an encrypted
  operator/user channel; public plaintext-HTTP access with real tenant data is
  NO-GO (UIX4-R019).
