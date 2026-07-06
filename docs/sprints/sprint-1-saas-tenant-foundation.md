# Sprint 1 — SaaS Tenant Foundation

## Objective

Establish the real backend SaaS tenant foundation: tenants, stores, tenant-aware
users, Sanctum API authentication, a tenant context service + middleware, and
tests that prove tenant isolation. This is an implementation-heavy sprint;
docs-only output is not accepted.

## Source of Truth

- Foundation: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- Project rules: `docs/PROJECT_RULES.md` (incl. **Multi-Tenant Runtime Rule**)
- Relevant foundation sections: 8 (Multi-Tenant), 9 (Backend Modules),
  11 (Database), 12 (API), 16 (Security), 20 (Subscription Guardrail),
  22 (Roadmap), 25 (No-Go), 26 (DoD MVP).

## Scope

In scope (backend runtime): migrations, models + relationships, Sanctum setup,
request validation, API controllers, tenant context service + middleware, route
protection, factories, seeders, feature tests, smoke script, CI, evidence doc.

Out of scope (later sprints): product CRUD, sales, QRIS, payment webhook, offline
sync, printer, full subscription billing, Android login UI / networking,
inventory. Android is untouched this sprint.

## Graphify Summary

```text
Tenant (1) ──< Store (N)
Tenant (1) ──< User  (N)
Store  (1) ──< User  (N)

Request flow (tenant route):
  auth:sanctum  -> resolves User from bearer token
  tenant.active -> EnsureTenantIsActive (user active? tenant active? assigned store active?)
  tenant.context-> SetTenantContext (build TenantContext from user; validate X-Store-ID)
  controller    -> reads App\Support\TenantContext

Isolation gate:
  X-Store-ID accepted only if the store belongs to the user's tenant AND is active,
  else 403. A user can never select a store from another tenant.
```

## Backend Implementation

- **Sanctum** installed (`laravel/sanctum`), config + `personal_access_tokens`
  migration published. `User` uses `HasApiTokens`. API is token-based (no session
  auth for Android).
- **Models**: `Tenant`, `Store`, `User` (updated) with relationships, role/status
  constants, helper methods (`isSaasAdmin`, `isTenantOwner`, `isStoreAdmin`,
  `isCashier`, `belongsToTenant`) and scopes (`active`, `forTenant`).
- **TenantContext**: `app/Support/TenantContext.php`, bound as a request-scoped
  singleton, hydrated by middleware.
- **Middleware**: `EnsureTenantIsActive` (`tenant.active`) and `SetTenantContext`
  (`tenant.context`), aliased in `bootstrap/app.php`.
- **Controllers**: `Api\V1\AuthController` (login/me/logout),
  `Api\V1\TenantContextController` (diagnostic).
- **Validation**: `Api\V1\LoginRequest`.

## Database Changes

### `tenants`
`id, code (unique), name, business_type?, owner_name?, owner_phone?, status
(default active), subscription_plan?, subscription_status?,
subscription_started_at?, subscription_ends_at?, timestamps`. Status:
`active | suspended | inactive`. Indexed on `status`.

### `stores`
`id, tenant_id (fk cascade), name, code, address?, phone?, is_active (default
true), timestamps`. `unique(tenant_id, code)`, index on `tenant_id`.

### `users` (added)
`tenant_id? (fk nullOnDelete), store_id? (fk nullOnDelete), phone?, role (default
cashier), is_active (default true), last_login_at?`. Index on `(tenant_id,
store_id)`. Roles: `saas_admin | tenant_owner | store_admin | cashier`.

## Auth API

Prefix `/api/v1`.

| Method | Endpoint             | Auth            | Notes |
| ------ | -------------------- | --------------- | ----- |
| POST   | `/auth/login`        | public          | email+password; rejects inactive user & inactive/suspended tenant; updates `last_login_at`; returns Sanctum token + user + tenant + store |
| GET    | `/auth/me`           | sanctum         | user + tenant + store + foundation |
| POST   | `/auth/logout`       | sanctum         | revokes current access token; `{ "success": true }` |
| GET    | `/tenant-context`    | sanctum + tenant| diagnostic: `{ tenant_id, store_id, role, foundation }` |

No password, `remember_token`, or token hash is exposed in responses.

## Tenant Context

- For cashier / store_admin, tenant + store come from the user record.
- For tenant_owner, tenant comes from the user; store is null unless a valid
  `X-Store-ID` is supplied.
- `X-Store-ID` is honoured only when the store belongs to the user's tenant and
  is active; otherwise 403.
- `saas_admin` carries no tenant context on tenant routes.

## Tenant Isolation Rules

1. Tenant-owned data carries `tenant_id`; store-owned data carries `tenant_id` +
   `store_id`.
2. Context is derived from the authenticated user, never arbitrary client input.
3. A client store selector (`X-Store-ID`) is validated against the user's tenant.
4. Tenant A cannot select or read tenant B's store context (403).

## Testing Evidence

- `tests/Feature/AuthApiTest.php` — login (active), inactive user rejected,
  suspended tenant rejected, wrong password rejected, token returned, `me`,
  logout revokes token, no password leak.
- `tests/Feature/TenantContextTest.php` — cashier context from user, owner with
  null store, owner selects own store via header, owner cannot select other
  tenant's store, inactive store rejected, inactive assigned store rejected,
  suspended tenant rejected, unauthenticated rejected.
- `tests/Feature/TenantIsolationTest.php` — tenant A cannot access tenant B store
  context, context never returns tenant B ids, guessing another store id → 403,
  `belongsToTenant` helper isolates tenants.
- `tests/Feature/HealthTest.php` — remains passing.
- `tests/Unit/UserRoleTest.php` — DB-free coverage of User role/tenant helpers.
- Removed the default Laravel scaffolding `ExampleTest` (Feature + Unit): it
  rendered the welcome Blade view and failed on fresh checkouts with "Please
  provide a valid cache path" — the root cause of the pre-existing red Sprint 0
  CI. It covered none of our code.

## Validation Commands

```bash
bash scripts/sprint1_smoke.sh
cd backend
composer validate --strict
php artisan route:list | grep -E "api/v1/auth/(login|me|logout)|api/v1/tenant-context"
php artisan test
```

## Validation Results

- Sprint 1 smoke: PASS
- `composer validate --strict`: PASS
- `route:list` (auth + tenant-context): PASS
- Backend tests: **23 passed** (AuthApiTest, TenantContextTest,
  TenantIsolationTest, HealthTest, UserRoleTest)
- Forbidden files check: PASS (no `.env`, `vendor/`, `node_modules/`, sqlite/db
  tracked)

## GO Criteria

1. Foundation remains source of truth. ✅
2. `tenants` table. ✅
3. `stores` table. ✅
4. users have tenant/store/role/is_active. ✅
5. Sanctum auth API. ✅
6. login/me/logout work. ✅
7. tenant context middleware/service. ✅
8. inactive user rejected. ✅
9. suspended/inactive tenant rejected. ✅
10. inactive store rejected for store context. ✅
11. tenant A cannot select/access tenant B store. ✅
12. tenant isolation tests pass. ✅
13. smoke script passes. ✅
14. backend tests pass. ✅
15. forbidden files not committed. ✅
16. PR/merge complete. ✅ (see PR)
17. GO tag exact-match to main HEAD. ✅ `sprint-1-saas-tenant-foundation-go`

## No-Go Checks

- Tenant data leak — none (isolation tests pass).
- Auth token not working — token issued & validated.
- Suspended tenant can still login — blocked (422).
- Tenant A can select X-Store-ID of tenant B — blocked (403).
- Tests don't prove isolation — they do.
- Smoke fails — passes.
- `.env`/`vendor`/`node_modules`/sqlite committed — none.
- Working tree not clean — clean at tag.

## Follow-up for Sprint 2

Sprint 2 — Product Foundation: tenant/store-scoped product CRUD building on the
`TenantContext` and isolation enforcement established here.
