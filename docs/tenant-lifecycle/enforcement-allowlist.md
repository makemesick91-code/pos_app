# Tenant Lifecycle Enforcement Allowlist (Sprint 25)

The runtime `tenant.lifecycle` guard (`EnsureTenantLifecycleAllowed`) wraps the
**operational** tenant route group in `routes/api.php` (the Sprint 10
`subscription.active` + `device.registered` business surface). A manually
suspended tenant is blocked there with `423 Locked` / `TENANT_SUSPENDED`.

The following routes are the **explicit allowlist** — they must stay reachable
while a tenant is suspended (TLS-R007, TLS-R008), so a suspended tenant can still
authenticate, see its status, and manage devices, and so platform/technical
surfaces are never accidentally locked:

| Route                                    | Why allowlisted                          |
|------------------------------------------|-------------------------------------------|
| `GET  /api/v1/health`                    | Health/technical.                         |
| `POST /api/v1/auth/login`                | Must be able to authenticate.             |
| `GET  /api/v1/auth/me`                   | Identity/session.                         |
| `POST /api/v1/auth/logout`               | Session teardown.                         |
| `GET  /api/v1/tenant-context`            | Diagnostic context.                       |
| `GET  /api/v1/subscription/status`       | See own subscription/lifecycle state.     |
| `POST /api/v1/devices/register`          | Device management.                        |
| `POST /api/v1/devices/heartbeat`         | Device management.                        |
| `GET  /api/v1/devices`                   | Device management.                        |
| `POST /api/v1/webhooks/payments/{provider}` | Billing callback — outside auth group; never tenant-locked. |
| `/api/v1/admin/*` (platform admin)       | Platform admins carry no tenant context; guard passes through. |

These routes live **outside** the operational group, so they are not wrapped by
`tenant.lifecycle`. This is verified continuously by
`tenant-lifecycle:enforcement-audit`, which FAILs if any operational route is
missing the guard, and by the Sprint 25 feature tests, which assert an allowlisted
route (`subscription/status`) stays reachable while POS routes return 423.

Platform/admin routes are never locked because the guard reads the per-request
`TenantContext`, which is `null` for `saas_admin` users.
