# UIX-4 — Tenant Owner Web Console (Architecture)

## Overview
A fifth Aish POS surface: a session/cookie browser console at `/owner/*` for a
tenant's owner to monitor their own business, read-only and tenant-scoped.

## Request flow
```
Route (/owner/*)
  → owner session guard
  → EnsureTenantOwnerWeb (tenant.owner.web): is_active + role=tenant_owner + tenant
  → OwnerController::context() → OwnerContextResolver::require()
        (tenant resolved from Auth::guard('owner')->user()->tenant, server-side only)
  → OwnerConsoleReadService (orchestrates canonical domain-service reads)
  → tenant-scoped Blade view (owner/*), no-store cache headers
```

## Components
| Concern | Class |
|---------|-------|
| Guard | `owner` (config/auth.php), session driver, users provider |
| Middleware | `App\Http\Middleware\EnsureTenantOwnerWeb` (alias `tenant.owner.web`) |
| Login | `App\Http\Controllers\Owner\OwnerLoginController` + `OwnerLoginRequest` |
| Context | `App\Services\OwnerConsole\OwnerContextResolver` → `OwnerContext` |
| Read model | `App\Services\OwnerConsole\OwnerConsoleReadService` |
| Controllers | `Owner\OwnerController` base + Dashboard/Outlet/Device/Subscription/Usage/Operations |
| Provisioning | `App\Console\Commands\TenantOwnerProvisionCommand` (`tenant:owner-provision`) |
| Views | `resources/views/owner/*` (build-free Blade, inline `aish-tokens.css`) |
| Audit | `App\Services\Admin\AdminAuditLogger` (ACTION_OWNER_LOGIN/LOGOUT/PROVISIONED) |

## Surface separation
The owner console uses a dedicated `owner` session guard, independent from the
platform-admin `web` guard. `EnsureTenantOwnerWeb` reads `Auth::guard('owner')`,
`EnsurePlatformAdminWeb` reads `Auth::guard('web')`. A session on one guard is
not authenticated on the other, so cross-surface access is impossible at the
session layer (not merely hidden navigation). A Sanctum API token authenticates
neither web guard.

## Tenant context & multi-membership
The domain models one tenant per user (scalar `users.tenant_id`, no pivot). The
resolver derives the tenant from the owner's own record; there is no
client-supplied tenant selector and no switcher. Multi-membership is documented
as a future extension requiring a pivot table and a `TenantContext` refactor —
it is not faked.

## Data sources (source of truth reused)
Lifecycle (`TenantLifecycleService`), plan (`TenantPlanResolver`),
entitlement/usage (`EntitlementUsageService`), billing/onboarding/android-runtime
(`SupportOperations` viewers), health (`SupportTenantHealthService`), sales
(`DailySalesReportService`), outlets (`Store` scoped query). The read service
computes nothing and mutates nothing; unavailable reads degrade to truthful
"Tidak tersedia" states.

## Route map
```
GET  /owner/login            owner.login
POST /owner/login            owner.login.store
POST /owner/logout           owner.logout            (tenant.owner.web)
GET  /owner                  owner.dashboard         (tenant.owner.web)
GET  /owner/outlets          owner.outlets.index
GET  /owner/outlets/{outlet} owner.outlets.show      (numeric, tenant-scoped)
GET  /owner/devices          owner.devices.index
GET  /owner/devices/{device} owner.devices.show      (numeric, tenant-scoped)
GET  /owner/subscription     owner.subscription
GET  /owner/usage            owner.usage
GET  /owner/operations       owner.operations
```
