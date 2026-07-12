# UIX-4 — Tenant Owner Web Console (Foundation)

Aish POS gains a fifth application surface: a **Tenant Owner Web Console** at
`/owner/*`. It is a session/cookie browser surface for a tenant's owner to
monitor their own business — outlets, devices, subscription, entitlement/usage,
billing, and operational health — read-only, tenant-scoped, and fully separate
from the Platform Admin Console, the public website, and the Android/API
(Sanctum) surface.

## Surface map (post UIX-4)

1. Public marketing website — Blade, no privileged data.
2. Platform Admin Console — `/admin/*`, `web` guard, `platform.admin.web`.
3. **Tenant Owner Web Console — `/owner/*`, `owner` guard, `tenant.owner.web`.**
4. Android / tenant API — Sanctum tokens, tenant-scoped.
5. Platform Admin API — `/api/v1/admin/*`, Sanctum + `platform.admin`.

## Identity & tenancy model

- Owner identity predicate = `users.is_active` **AND** `role = tenant_owner`
  (`User::ROLE_TENANT_OWNER`) **AND** a resolvable `tenant_id`. It is never a
  platform capability and never a tenant permission string sprinkled across
  controllers — it is checked once in `EnsureTenantOwnerWeb` and re-validated in
  `OwnerContextResolver`.
- The console runs on a dedicated `owner` session guard, separate from the
  platform-admin `web` guard, so neither session can satisfy the other's gate.
- The domain models exactly **one tenant per user** (a scalar `users.tenant_id`
  foreign key; there is no membership pivot). UIX-4 therefore implements a
  single-authorized-tenant context and no tenant switcher. Multi-membership is
  intentionally NOT faked; adding it would require a new pivot table and a
  refactor of `TenantContext`/`SetTenantContext`.
- The tenant is always derived server-side from the authenticated owner's own
  record. No route parameter, query string, header, cookie, or hidden field can
  select or switch it. Outlet/device ids are resolved only within the owner's
  tenant (a foreign id resolves to 404).

## Data sources (canonical services reused, never forked)

| Capability     | Canonical source of truth |
|----------------|---------------------------|
| Lifecycle      | `TenantLifecycleService::resolve()` |
| Plan           | `TenantPlan\TenantPlanResolver::resolve()` |
| Entitlement/usage | `Entitlements\EntitlementUsageService::summary()` |
| Billing        | `SupportOperations\SupportBillingViewerService::summary()` |
| Onboarding     | `SupportOperations\SupportOnboardingViewerService::summary()` |
| Devices/sync   | `SupportOperations\SupportAndroidRuntimeViewerService` |
| Health         | `SupportOperations\SupportTenantHealthService::overview()` |
| Sales summary  | `Reports\DailySalesReportService::summary()` |
| Outlets        | `Store` (tenant-scoped query) |

`App\Services\OwnerConsole\OwnerConsoleReadService` orchestrates and shapes these
reads for presentation only; it computes no business state and mutates nothing.

## UIX-4 rule set (UIX4-R001..R022)

- **UIX4-R001** — Tenant Owner Web Console is a distinct application surface.
- **UIX4-R002** — Tenant Owner access never implies Platform Admin access.
- **UIX4-R003** — Platform Admin access never implies Tenant Owner membership.
- **UIX4-R004** — Tenant context comes only from authorized server-side membership.
- **UIX4-R005** — Raw tenant IDs from request input are never trusted.
- **UIX4-R006** — Every owner query is tenant-scoped and deny-by-default.
- **UIX4-R007** — Route-model binding must enforce active tenant membership.
- **UIX4-R008** — Multi-tenant owner switching must validate every target membership.
- **UIX4-R009** — Existing domain services remain the source of truth.
- **UIX4-R010** — Dashboard values must be truthful; unavailable is not zero.
- **UIX4-R011** — UIX-4 is read-only first unless a governed mutation service exists.
- **UIX4-R012** — Production default owner credentials are forbidden.
- **UIX4-R013** — Owner web authentication requires session regeneration, CSRF,
  throttling, secure logout, and generic failure messaging.
- **UIX4-R014** — Authenticated owner pages must prevent sensitive browser caching.
- **UIX4-R015** — Cache keys and cached data must include tenant and identity scope.
- **UIX4-R016** — Audit logs must redact credentials, tokens, cookies, and sensitive PII.
- **UIX4-R017** — Responsive and accessibility gates are mandatory.
- **UIX4-R018** — Cross-tenant and role-separation tests are release blockers.
- **UIX4-R019** — Public plaintext HTTP use with real tenant data is NO-GO.
- **UIX4-R020** — Shared-VPS deployment must not change or regress DaengtisiaMS.
- **UIX4-R021** — GO requires local/origin/VPS exact match and runtime verification.
- **UIX4-R022** — Existing GO tags are immutable.

## Verification

- Design gate: `scripts/uix4_design_gate.sh` (chains UIX-3 → UIX-2 → UIX-1).
- Foundation gate: `scripts/verify_application_foundation_rules.sh` (extended for
  the owner surface).
- Targeted tests: `php artisan test --filter=Uix4`.
- Authoritative CI: `.github/workflows/uix4-ci.yml` plus the existing
  `pull_request` workflows.
