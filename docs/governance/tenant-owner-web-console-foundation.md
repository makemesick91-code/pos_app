# Tenant Owner Web Console — Governance Foundation (UIX-4)

This document is the governance synthesis for the Tenant Owner Web Console. The
enforceable rule set (UIX4-R001..R022) and the architecture live in
`docs/foundation/uix-4-tenant-owner-web-console.md`; the boundary rule lives in
`.claude/rules/25-tenant-owner-web-console-boundary.md`.

## Surface boundaries
Aish POS has five surfaces. The Tenant Owner Web Console (`/owner/*`) is distinct
from the public website, the Platform Admin Console (`/admin/*`), the Platform
Admin API, and the Android/tenant API. It runs on the dedicated `owner` session
guard so its session cannot satisfy the platform-admin gate and vice versa.

## Identity & membership
- Owner identity = `users.is_active` AND `role = tenant_owner`
  (`User::ROLE_TENANT_OWNER`) AND a resolvable `tenant_id`.
- It is never a platform capability, never a tenant permission string spread
  across controllers, and never grantable through a platform flow.
- Membership is a scalar `users.tenant_id` (one tenant per user; no pivot).

## Tenant context & multi-membership
- Tenant context is resolved server-side from the owner's own record by
  `OwnerContextResolver`. No route parameter, query string, header, cookie, or
  hidden field can select or switch it.
- Multi-membership is not modelled today, so no tenant switcher is exposed and
  none is faked. A future switcher would validate every target membership via
  POST+CSRF and re-scope cache/view state.

## Status / lifecycle behaviour
- The authoritative status is `TenantLifecycleService::resolve()`. A suspended /
  archived / blocked tenant owner may sign in and see status + billing, but
  business-data pages (outlets, devices) degrade to a truthful restricted view
  rather than exposing operational data.

## Data access & cache isolation
- Reads come only from the canonical services; the console recomputes nothing.
- Every query is explicitly tenant-scoped (no global scope exists). Any caching
  must include tenant + identity scope so a cached response cannot cross tenants.
- Device token hash and fingerprint hash are never surfaced.

## Audit & session security
- Login/logout are audited via `AdminAuditLogger` (sanitized); failed logins log
  a hashed identifier only. Sessions regenerate on login and invalidate on
  logout; authenticated pages are non-cacheable; CSRF protects state-changing
  requests.

## Read-only-first principle
- No tenant business mutation route exists. A future mutation requires an
  existing governed service, policy, tenant scope, audit, idempotency,
  confirmation UX, and tests before it may ship.

## Deployment transport restriction
- Shared-VPS isolation is preserved and DaengtisiaMS is non-regressed. While
  HTTPS/domain is absent, the console is reachable only via an encrypted
  operator/user channel; public plaintext-HTTP access with real tenant data is
  NO-GO.

## Release governance
- GO requires green authoritative CI, deploy, runtime verification, DMS
  non-regression, real evidence, and local/origin/VPS exact-match. Existing GO
  tags are immutable.
