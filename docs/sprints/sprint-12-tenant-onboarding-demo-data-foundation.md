# Sprint 12 — Tenant Onboarding & Demo Data Foundation

## Objective

Build the backend foundation for **platform-admin-controlled tenant onboarding**
and **safe demo data**: a single admin action creates a tenant, its default
store, an owner user, and an initial subscription in one transaction, then
optionally seeds a tenant-owned demo catalog with opening inventory. Onboarding
is idempotent, every action is audit-logged, and demo data can be reset safely
without ever touching production data.

No public self-service signup, no real billing, no email/WhatsApp invites, no
tenant impersonation, and no Android onboarding/admin UI.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (POS_ANDROID_SAAS_FOUNDATION)
- `docs/PROJECT_RULES.md`
- Sprint 0–11 evidence docs (Foundation Lock Index)

## Previous Sprint Foundation Lock

Sprint 12 builds on and must not break:

- Sprint 1 Tenant/Store/User + auth foundation
- Sprint 2 Product/ProductCategory/ProductStorePrice
- Sprint 4 Sale/SaleItem/Payment
- Sprint 8 InventoryMovement ledger (stock is the signed sum of movements)
- Sprint 9 Reports & Closing
- Sprint 10 SubscriptionPlan/TenantSubscription/RegisteredDevice enforcement
- Sprint 11 platform admin (`platform.admin`) + AdminAuditLog

## Scope

In scope (backend only):

- `tenant_onboarding_runs` table + `TenantOnboardingRun` model + factory
- Onboarding services (orchestrator, checklist, demo seeder, demo reset, demo catalog)
- Admin onboarding + demo-data APIs under `/api/v1/admin`
- `tenants:onboard-demo` artisan command
- Audit logging for onboarding/seed/reset
- Tests, smoke script, CI, documentation, PROJECT_RULES lock

Out of scope (No-Go): public signup, tenant self-registration, email/WhatsApp
invites, real billing/charging, Play Billing, tenant self-service billing portal,
onboarding wizard UI, full web admin dashboard, Android onboarding/admin panel,
destructive hard tenant delete, production reset without demo guard, impersonation.

## Graphify Summary

```
Platform Admin (platform.admin)
  └─ POST /admin/tenant-onboarding  (idempotent by onboarding_reference)
       └─ TenantOnboardingService.onboard()
            ├─ TenantOnboardingRun (PENDING → RUNNING)
            ├─ DB::transaction
            │    ├─ Tenant.create
            │    ├─ Store.create (default store)
            │    ├─ User.create (tenant_owner, hashed password)
            │    ├─ TenantSubscription.create (TRIAL/ACTIVE/GRACE)
            │    └─ [demo] DemoDataSeederService.seed()
            │           ├─ ProductCategory (Minuman/Makanan/Jasa)
            │           ├─ Product (DEMO-* sku)
            │           ├─ ProductStorePrice (default store)
            │           └─ InventoryMovementService.createOpeningMovement (OPENING)
            ├─ TenantOnboardingChecklistService.buildForTenant()  (backend-derived)
            ├─ Run → COMPLETED (+ demo_manifest in metadata)
            └─ AdminAuditLogger.log(TENANT_ONBOARDED)   (no password)

  └─ POST /admin/tenants/{tenant}/demo-data        → DemoDataSeederService (+manifest run)
  └─ POST /admin/tenants/{tenant}/demo-data/reset  → DemoDataResetService (guarded, manifest-only)
  └─ GET  /admin/tenants/{tenant}/onboarding-status→ TenantOnboardingChecklistService
```

## Backend Implementation

### Tenant Onboarding Run

`create_tenant_onboarding_runs_table` migration + `App\Models\TenantOnboardingRun`:

- Unique `onboarding_reference` (idempotency key).
- FKs: `requested_by` (platform admin), nullable `tenant_id`, `default_store_id`,
  `owner_user_id`, `subscription_plan_id`, `tenant_subscription_id`.
- `status` PENDING | RUNNING | COMPLETED | FAILED with `markRunning/markCompleted/markFailed`.
- `checklist` (json, backend-generated), `metadata` (json, holds the demo manifest;
  never a password/secret), `demo_data_seeded_at`, `demo_data_reset_at`, timestamps.
- Relationships: `requester`, `tenant`, `defaultStore`, `ownerUser`,
  `subscriptionPlan`, `tenantSubscription`; `Tenant hasMany onboardingRuns`,
  `User hasMany requestedOnboardings`.

### Onboarding Service

`TenantOnboardingService::onboard()`:

- Looks up the reference first; an existing run is returned as an **idempotent
  replay** (never creating a second tenant), and is audit-logged as
  `TENANT_ONBOARDING_REPLAYED`.
- Otherwise wraps tenant/store/owner/subscription creation (and optional demo
  seeding) in a single `DB::transaction`. A failure marks the run FAILED with a
  safe error message and rethrows.
- Owner password is write-only input — hashed with `Hash::make`, never stored in
  metadata/checklist or the audit log.

### Default Tenant/Store/User Creation

Created inside the transaction: active Tenant (from `tenant_code`), default Store
(`{tenant_code}-01`), and an active `tenant_owner` User bound to the tenant/store.

### Subscription Assignment

A `TenantSubscription` is created for the chosen plan. TRIAL sets
`trial_ends_at = now + trial_days` (default 14); ACTIVE sets `ends_at = now + 1
month`. The authoritative allowed/blocked decision remains
`SubscriptionStatusService` (Sprint 10) — the persisted status is only a hint.

### Demo Product Data

`DemoCatalogFactory` defines a deterministic catalog (categories Minuman/Makanan/
Jasa; products Kopi Susu / Teh Manis / Roti Bakar / Cuci Sepatu, DEMO- sku
prefix). `DemoDataSeederService` seeds it tenant-owned and **idempotently**
(`firstOrCreate` by (tenant_id, sku) / (tenant_id, store_id, name);
`updateOrCreate` prices) so repeated seeds never create unlimited duplicates.

### Demo Opening Inventory

Opening stock is written **only** through
`InventoryMovementService::createOpeningMovement` (movement type `OPENING`) — no
mutable stock column. Seeding is idempotent: one OPENING per (tenant, store,
product), skipped if one already exists.

### Optional Demo Sales/Reports

**Deferred by design.** Creating paid demo sales server-side would have to
reproduce or bypass sales/payment/inventory/report semantics, so Sprint 12 does
not create demo sales. The `seed_demo_sales` flag is accepted but recorded as a
`demo_sales_deferred` note. Reporting APIs remain fully functional; demo opening
inventory gives the reports real ledger data.

### Demo Reset Safety

`DemoDataResetService` deletes **only** rows recorded in the backend-owned demo
manifest (ids seeded by an onboarding/demo-seed run for this tenant) **and** still
owned by the tenant. Data never seeded by onboarding is never in a manifest, so it
can never be deleted. Supports `dry_run` (counts only), audit-logs every reset,
and has no destructive tenant wipe. Guarded by `confirm_demo_reset` (422 when not
accepted).

### Idempotency Rules

- `onboarding_reference` is unique; a replay returns HTTP 200 with
  `meta.idempotent_replay=true`; a new onboarding returns HTTP 201 with
  `meta.idempotent_replay=false`.
- Uniqueness checks on `tenant_code`/`owner_email` are skipped on replay (the
  first run's own records would otherwise falsely collide).
- Demo seeding is idempotent (stable keys), so repeated seeds keep the manifest
  stable and never multiply rows.

### Audit Logging Rules

New `AdminAuditLog` actions: `TENANT_ONBOARDED`, `TENANT_ONBOARDING_REPLAYED`,
`DEMO_DATA_SEEDED`, `DEMO_DATA_RESET` (+ target `tenant_onboarding_run`). All go
through `AdminAuditLogger`, which strips password/secret-looking keys — password
is never present in any snapshot.

### Tenant Isolation Rules

Every seeded row is created for the target tenant/store; the reset delete queries
are scoped to `tenant_id` **and** the manifest ids (defence in depth). Tests prove
demo data does not appear for other tenants and reset never touches non-demo data.

## No Public Signup Decision

There is no public/self-service signup endpoint. Onboarding is reachable only
under `/api/v1/admin/*` behind `auth:sanctum` + `platform.admin`. Tenant users get
403 `PLATFORM_ADMIN_REQUIRED`.

## No Android Onboarding/Admin UI Decision

By design, no Android onboarding/admin panel UI is added (no `AdminActivity`, no
`OnboardingActivity`, no tenant management UI). The POS device app keeps its
normal tenant/business behavior. Android CI remains the authoritative build gate.

## Android Build CI Evidence

`.github/workflows/sprint12-ci.yml` runs three jobs:
`foundation-and-smoke` (`scripts/sprint12_smoke.sh`), `backend-tests`
(`composer validate --strict` + `php artisan test`, PHP 8.5), and
`android-build-test` (`./gradlew :app:assembleDebug` + `:app:testDebugUnitTest`,
JDK 21). Android build/test is required (no `continue-on-error`).

## Application Rules Update

- `docs/PROJECT_RULES.md`: Foundation Lock Index now lists Sprint 12; added the
  **Sprint 12 Tenant Onboarding & Demo Data Foundation Runtime Rule** (21 rules).
- `backend/config/pos_foundation.php`: added `sprint_12` plus onboarding/demo rule
  flags.

## Testing Evidence

Feature tests (all green):

- `TenantOnboardingApiTest` — creates tenant/store/owner/subscription, demo seed,
  checklist populated, no password exposed, no password in metadata/checklist.
- `TenantOnboardingIdempotencyTest` — replay returns same run, no duplicate
  tenant/user/store/subscription.
- `TenantDemoDataApiTest` — tenant-owned demo data, OPENING via ledger, idempotent
  reseed, store/tenant mismatch rejected, no cross-tenant leak.
- `TenantDemoDataResetTest` — confirmation required, removes demo data, never
  deletes non-demo data, dry-run counts, audit-logged.
- `TenantOnboardingAuditLogTest` — onboarding/seed/reset audit-logged, no secret.
- `TenantOnboardingAuthorizationTest` — 401/403 gating, tenant user blocked.
- `TenantOnboardingRegressionTest` — Sprint 11 admin still protected, onboarded
  owner can login + subscription active, Sprint 10 device gate enforced then
  works, existing business API unbroken.

## Backend Compatibility Evidence

Full backend suite green: **273 tests / 960 assertions**. All Sprint 0–11 APIs
listed in the sprint prompt continue to route and pass.

## Validation Commands

```bash
bash scripts/sprint12_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- `composer validate --strict`: valid
- `php artisan test`: 273 passed
- Sprint 12 tests: 23 passed
- Smoke: pass (see CI `foundation-and-smoke`)
- Android build/test: authoritative in `sprint12-ci` (JDK 21)

## GO Criteria

All Sprint 12 GO criteria met: onboarding table/model/services present, admin
onboarding + demo-data APIs present and platform-admin only, onboarding
transaction-safe and idempotent, demo data tenant-owned with ledger-based opening
stock, demo reset guarded and non-destructive to non-demo data, all actions
audit-logged, no password returned/stored, no public signup, no real billing, no
invites, no Android onboarding/admin UI, previous behavior intact, backend tests
pass, Android CI green.

## No-Go Checks

None triggered: rules intact, onboarding not reachable by tenant users, no public
signup, transaction-safe, no duplicate on replay, no cross-tenant leak, opening
stock uses `inventory_movements`, reset cannot delete non-demo data, all actions
audit-logged, no password in metadata/audit/resource, no real billing/invites, no
Android admin/onboarding UI, Sprint 10/11 enforcement intact.

## Follow-up for Sprint 13

- Optional safe demo sales via a dedicated demo sales service (with report
  reconciliation) if product wants populated demo dashboards.
- Optional invite/notification foundation (still no real send in dev).
- Admin onboarding UI is out of scope for the Android POS device app.
