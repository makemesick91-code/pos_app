# UIX-3 — Platform Admin SaaS Control Center (Architecture)

Status: implemented vertical slice (read-only foundation). Distinct surface from the
Sanctum API and from the public marketing website.

## 1. Surfaces

UIX-3 introduces a browser (session/cookie) surface for platform operators only.
It is served from `backend/routes/web.php` under the `/admin` prefix with route
names `admin.*`.

| Surface | Route | Name | Controller |
| --- | --- | --- | --- |
| Login page | `GET /admin/login` | `admin.login` | `App\Http\Controllers\Admin\AdminLoginController@showLogin` |
| Login submit | `POST /admin/login` | `admin.login.store` | `AdminLoginController@login` |
| Logout | `POST /admin/logout` | `admin.logout` | `AdminLoginController@logout` |
| Dashboard | `GET /admin` | `admin.dashboard` | `AdminDashboardController@index` |
| Tenant list | `GET /admin/tenants` | `admin.tenants.index` | `AdminTenantWebController@index` |
| Tenant detail | `GET /admin/tenants/{tenant}` | `admin.tenants.show` | `AdminTenantWebController@show` |

Everything except the login page (and the login submit) is behind the middleware
alias `platform.admin.web` = `App\Http\Middleware\EnsurePlatformAdminWeb`. The
logout route is also gated so only an authenticated admin session can trigger it.

Separately, `GET /health/live` and `GET /health/ready` (Sprint 36) remain public
and minimal — they return only `{ status, timestamp }` and are used for smoke
checks. UIX-3 does not modify them.

## 2. Request flow: route -> middleware -> controller -> service -> view

```
Browser (operator over an encrypted channel: SSH tunnel / VPN / private network)
  │
  ▼
web middleware group (session, cookies, CSRF verification)
  │
  ▼
platform.admin.web  = EnsurePlatformAdminWeb
  • Auth::guard('web')->user()
  • null              -> redirect()->guest(admin.login)   [deny by default]
  • !is_active        -> logout + session invalidate + regen token -> admin.login
  • !isPlatformAdmin  -> logout + session invalidate + regen token -> admin.login
  • ok                -> next(), then sets
                         Cache-Control: no-store, no-cache, must-revalidate, private
                         Pragma: no-cache
  │
  ▼
Controller (thin: no business status computed here)
  │
  ▼
Governed read services (canonical summaries + domain redactors already applied)
  │
  ▼
Blade view (build-free, extends resources/views/admin/layout.blade.php)
```

The login page itself is a standalone Blade page (not inside the console shell)
so an unauthenticated visitor never sees console chrome.

## 3. Reuse map (capability -> service reused)

The Control Center computes **no** business status of its own. Each capability is
delegated to an existing governed service; the console only assembles and renders.

### Dashboard — `App\Services\Admin\ControlCenterMetricsService::overview()`

Every group is resolved inside a `guard()` wrapper: on any error or unavailable
source it returns `{ available: false }` so the UI renders an explicit
"unavailable" state, never a fabricated `0` (truthfulness contract UIX-R024/R025).

| Dashboard group | Source service / query |
| --- | --- |
| Tenants (grouped counts) | `App\Models\Tenant` grouped counts |
| Trials | `TenantOnboarding\OnboardingSummaryService::trialSummary` |
| Billing | `Billing\BillingSummaryService::invoiceSummary` |
| Settlement | `PaymentGateway\PaymentGatewaySummaryService::settlementSummary` |
| Devices | `AndroidRuntime\AndroidRuntimeSummaryService::deviceSummary` |
| Stores | `App\Models\Store` count |
| Support incidents | `App\Models\TenantSupportIncident` open count |
| Queue health | `Observability\QueueHealthService::summary` |
| System health | `Observability\ObservabilityHealthService::overview` |
| Suspensions | `TenantLifecycle\TenantSuspensionSummaryService::summary` |

Aggregate queries are bounded (grouped counts, no per-tenant fan-out) so the
dashboard never degrades into N+1.

### Tenant list — `AdminTenantWebController@index`

- `Admin\AdminTenantService::paginate(...)` for the cross-tenant paginated query
  (reused from Sprint 11). Sort columns are whitelisted (`id, name, code,
  created_at, updated_at`); status filter is whitelisted
  (`active, suspended, inactive`); `per_page` is clamped to 10..50.
- For each **paginated** row (bounded to page size): authoritative
  `TenantLifecycle\TenantLifecycleService::resolve($tenant)` — lifecycle is never
  recomputed inline — plus `subscriptionSummary`.

### Tenant detail — `AdminTenantWebController@show`

- `SupportOperations\SupportTenantHealthService::overview($tenant)` fuses the
  billing / payment / entitlement / onboarding / android dimensions and is
  already redacted (reused from Sprint 35).
- `TenantLifecycleService::resolve` + `subscriptionSummary`.
- Each supplemental read is wrapped in a `safe()` degrade-to-`{available:false}`
  guard so one failing summary does not fail the whole page.
- The cross-tenant read is attributed via `Admin\AdminAuditLogger` as
  `AdminAuditLog::ACTION_TENANT_VIEWED` (metadata sanitized by the logger; only
  tenant identifiers, no secrets/PII).

## 4. Why read-only

This is a foundation sprint. **No tenant mutation routes exist** in UIX-3. The
console reads across tenants by design (platform operator role); the only writes
it performs are audit records (login, logout, tenant-view). Consequences:

- No business logic is duplicated in the web layer — status/entitlement/billing
  decisions stay in their canonical services, so the console can never diverge
  from the authoritative API behaviour.
- The attack surface for the new surface is limited to authentication,
  authorization, and read queries. There is no state-changing endpoint to abuse.
- Truthfulness is preserved end to end: unavailable sources surface as explicit
  "unavailable", not as misleading zeros.

## 5. Blast radius

- **New code**: 3 admin controllers, 1 middleware, 1 metrics assembler service,
  1 provisioning command, 5 Blade views (`layout`, `login`, `dashboard`,
  `tenants/index`, `tenants/show`). No migrations — UIX-3 adds no tables.
- **Reused, unchanged**: all governed summary services, `AdminTenantService`,
  `TenantLifecycleService`, `SupportTenantHealthService`, `AdminAuditLogger`,
  `AdminAuditLog` model, `User` platform-admin predicate.
- **Isolation from tenant runtime**: the console has no code path into POS
  business mutations. A tenant business session cannot reach a platform route
  (predicate enforced before login and again in middleware).
- **Deployment blast radius**: no schema change, no queue/worker change; a
  rollback is a git fast-forward back plus an FPM/nginx reload (see
  `docs/deployment/uix-3-rollback.md`).
- **UI**: build-free Blade inlining `resources/css/aish-tokens.css`; no new build
  pipeline, no new asset bundle.
