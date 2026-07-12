# 20 — Multi-Tenancy & Authorization

Tenant isolation and the platform-admin boundary.

## Tenant isolation
- Every tenant request is scoped to the authenticated tenant. Queries for tenant data
  must be constrained to that tenant; never read or write another tenant's rows.
- Tenant identity comes from the Sanctum-authenticated user/token and device context,
  not from client-supplied tenant IDs in the request body.
- Cross-tenant aggregation is a platform-admin concern only, done through admin services.

## Tenant lifecycle enforcement
- `TenantLifecycleService::resolve()` is the single source of tenant status. Manual
  suspension wins over paid billing and is never lifted by a payment.
- The business guard chain (`tenant.lifecycle` → `tenant.entitled` → usage/limit) enforces:
  suspended = blocked (423), unpaid/trial-expired = read-only/degraded,
  over-quota = create blocked (429) while reads still succeed.

## Platform-admin boundary
- Platform admin = `users.is_platform_admin` (boolean) AND `is_active`. This is NEVER a
  tenant role or tenant permission, and must never be grantable through tenant flows.
- API enforcement: `App\Http\Middleware\EnsurePlatformAdmin` on `/api/v1/admin/*` +
  `platform.admin`. Web enforcement: `EnsurePlatformAdminWeb` on `/admin/*` +
  `platform.admin.web`. Do not inline or duplicate the predicate.
- Platform admins operate across tenants but through admin services; they do not
  impersonate tenant users (impersonation is disabled and returns 403).

## Admin surfaces default to read-only
- Support/observability/admin consoles are read-only by default. Any mutation must be an
  explicitly authorized, audited admin action — never an incidental side effect.

## Authorization placement
- Authorization is enforced by middleware + policies/service gates, not by hiding a button
  in Blade. A hidden UI element is never a substitute for a server-side check.
