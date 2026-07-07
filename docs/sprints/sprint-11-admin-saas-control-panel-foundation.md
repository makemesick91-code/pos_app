# Sprint 11 — Admin SaaS Control Panel Foundation

## Objective

Establish the backend foundation for the SaaS platform admin control panel:
platform admin authorization, cross-tenant tenant list/detail, subscription
assign/update, device list/revoke, subscription plan management, and an admin
audit log — all backend-authorized and audit-logged, separated from tenant
business APIs. No Android admin panel is introduced.

Flow:

```
Platform Admin → Tenant List/Detail → Subscription Assign/Update → Device List/Revoke → Plan Management → Audit Log
```

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (canonical)
- `docs/PROJECT_RULES.md` (cumulative runtime rules, Sprint 0–11)

Governing foundation sections: 8 (Multi-Tenant), 9 (Backend Modules), 12 (API),
16 (Security), 17 (Performance), 21 (MVP Scope), 22 (Roadmap), 25 (No-Go), 26
(Definition of Done).

## Previous Sprint Foundation Lock

Sprint 0–10 rules remain intact in `docs/PROJECT_RULES.md`. The Foundation Lock
Index now lists sprint-0 through sprint-11. Sprint 10 subscription/device
enforcement and Sprint 2–9 business behavior are preserved and covered by
regression tests.

## Scope

In scope (backend only):

- platform admin authorization (`is_platform_admin` flag + `isPlatformAdmin()` + `platform.admin` middleware)
- admin route group under `/api/v1/admin`
- admin tenant list/detail APIs
- admin subscription assign/update APIs
- admin device list/revoke APIs
- admin subscription plan list/create/update/deactivate APIs
- admin audit log table/model/service/resource/API
- audit sanitization (no secrets, no raw gateway payloads)
- feature tests + smoke + CI + rules lock + evidence

Out of scope (not implemented in Sprint 11): full web dashboard UI, Blade/React/Vue
admin app, Android admin panel, real subscription billing/charge/invoice, Play
Billing, proration, reseller portal, tenant self-service billing portal, advanced
admin analytics, destructive tenant hard delete, export-all-tenant-data, and
impersonation / login-as-tenant.

## Graphify Summary

- `User` gains `is_platform_admin` (+ granted/revoked timestamps) and
  `isPlatformAdmin()`; the legacy `saas_admin` role is **not** equivalent to a
  platform admin (verified by test).
- `EnsurePlatformAdmin` (alias `platform.admin`) runs after `auth:sanctum` on the
  admin route group; it does not apply `tenant.context`, so admins carry no
  tenant and read cross-tenant data only through admin services.
- Reuses Sprint 10 `Tenant`, `SubscriptionPlan`, `TenantSubscription`,
  `RegisteredDevice`, and `SubscriptionStatusService` (authoritative status).
- New `AdminAuditLog` model + `admin_audit_logs` table records every mutation.

## Backend Implementation

Migrations:

- `2026_07_07_400000_add_platform_admin_fields_to_users_table.php`
- `2026_07_07_400001_create_admin_audit_logs_table.php`

Model: `App\Models\AdminAuditLog` (actor/tenant relations, action + target
constants). `User::adminAuditLogs()` and `Tenant::adminAuditLogs()` added.

Services (`app/Services/Admin/`): `AdminAuditLogger`, `AdminTenantService`,
`AdminSubscriptionService`, `AdminDeviceService`, `AdminPlanService`.

Requests (`app/Http/Requests/Api/V1/Admin/`): `IndexAdminTenantRequest`,
`StoreAdminTenantSubscriptionRequest`, `UpdateAdminTenantSubscriptionRequest`,
`IndexAdminDeviceRequest`, `StoreAdminSubscriptionPlanRequest`,
`UpdateAdminSubscriptionPlanRequest`, `IndexAdminAuditLogRequest`.

Controllers (`app/Http/Controllers/Api/V1/Admin/`): `AdminTenantController`,
`AdminTenantSubscriptionController`, `AdminTenantDeviceController`,
`AdminSubscriptionPlanController`, `AdminAuditLogController`.

Resources (`app/Http/Resources/Api/V1/Admin/`): `AdminTenantResource`,
`AdminTenantDetailResource`, `AdminTenantSubscriptionResource`,
`AdminDeviceResource`, `AdminSubscriptionPlanResource`, `AdminAuditLogResource`.

Factories: `AdminAuditLogFactory`; `UserFactory::platformAdmin()`.

## Platform Admin Authorization

`platform.admin` (`EnsurePlatformAdmin`): requires `auth:sanctum`; the user must
be active and carry `is_platform_admin`. Non-admins receive a stable 403:

```json
{ "message": "Platform admin access required", "code": "PLATFORM_ADMIN_REQUIRED" }
```

There is no hardcoded admin email in code; the flag is set explicitly. A platform
admin is not a tenant business user — being a platform admin never grants access
to tenant business APIs (verified).

## Admin Route Group

`/api/v1/admin/*` under `auth:sanctum` + `platform.admin`. Not wrapped by
`tenant.active` / `tenant.context` / `subscription.active` / `device.registered`.

## Admin Tenant APIs

- `GET /api/v1/admin/tenants` — filters `q`, `status`, `subscription_status`, `limit` (max 100); summary rows with `stores_count`, `devices_active_count`, authoritative subscription summary.
- `GET /api/v1/admin/tenants/{tenant}` — detail with stores, subscription summary, device counts (`active_count`, `max_devices`). Viewing is audit-logged (`TENANT_VIEWED`).

## Admin Subscription APIs

- `GET /api/v1/admin/tenants/{tenant}/subscriptions`
- `POST /api/v1/admin/tenants/{tenant}/subscriptions` — assign plan; validates plan exists, status, date consistency; audit `SUBSCRIPTION_ASSIGNED`.
- `PATCH /api/v1/admin/tenants/{tenant}/subscriptions/{subscription}` — subscription must belong to tenant (else 404); status/date only; audit `SUBSCRIPTION_UPDATED` with before/after.

No real billing/charge/invoice is performed — foundation only.

## Admin Device APIs

- `GET /api/v1/admin/tenants/{tenant}/devices` — optional `status` filter.
- `POST /api/v1/admin/tenants/{tenant}/devices/{device}/revoke` — device must belong to tenant (else 404); sets `REVOKED` + `revoked_at`; idempotent; audit `DEVICE_REVOKED`. A revoked device no longer passes `device.registered` (verified).

## Admin Plan APIs

- `GET /api/v1/admin/subscription-plans`
- `POST /api/v1/admin/subscription-plans` — unique `code`, positive `max_stores`/`max_devices`; audit `PLAN_CREATED`.
- `PATCH /api/v1/admin/subscription-plans/{plan}` — audit `PLAN_UPDATED`.
- `POST /api/v1/admin/subscription-plans/{plan}/deactivate` — soft deactivate (`is_active=false`); audit `PLAN_DEACTIVATED`.

No hard delete endpoint exists (a `DELETE` yields 405, verified).

## Admin Audit Logs

- `GET /api/v1/admin/audit-logs` — filters `actor_user_id`, `action`, `target_type`, `tenant_id`, `from`, `to`, `limit` (max 100).
- `GET /api/v1/admin/audit-logs/{auditLog}`.

Each record stores actor, action, target type, target id, tenant context (when
available), sanitized before/after, IP, and timestamps.

## Audit Sanitization Rules

`AdminAuditLogger::sanitize()` recursively strips keys containing any of:
`password`, `secret`, `token`, `api_key`/`apikey`, `private_key`, `signature`,
`gateway_payload`/`raw_payload`/`payload`, `credential`, `server_key`,
`client_key`, `webhook`. Non-scalar leaves are dropped/stringified so no raw
gateway payload can be smuggled in. Services persist only safe status/date/plan
snapshots. (Verified by unit + resource tests.)

## Tenant User Blocking Rules

Tenant business users and unauthenticated requests never reach `/api/v1/admin/*`
(403 `PLATFORM_ADMIN_REQUIRED` / 401). The legacy `saas_admin` role alone does
not grant admin access.

## No Android Admin UI Decision

No Android admin panel is added by design. No `AdminActivity`, no tenant
management UI, no platform admin control panel on the POS device app. Android
retains all Sprint 1–10 tenant/business behavior. The backend admin contract
did not change any Android-consumed endpoint, so no Android runtime change was
required. Android CI remains the authoritative build gate.

## Android Build CI Evidence

`.github/workflows/sprint11-ci.yml` job `android-build-test` runs (JDK 21):

```bash
cd android
chmod +x ./gradlew
./gradlew :app:assembleDebug
./gradlew :app:testDebugUnitTest
```

Not optional; no `continue-on-error`.

## Application Rules Update

`docs/PROJECT_RULES.md`: Foundation Lock Index extended to sprint-11; new
"Sprint 11 Admin SaaS Control Panel Foundation Runtime Rule" appended; Sprint
0–10 rules unchanged. `backend/config/pos_foundation.php` adds sprint_11 and the
`platform_admin_required`, `admin_audit_log_required`,
`admin_no_tenant_impersonation_sprint_11`, `admin_no_real_billing_collection_sprint_11`,
`admin_no_tenant_hard_delete_sprint_11` flags.

## Testing Evidence

Feature tests (`backend/tests/Feature/`):

- `AdminPlatformAuthorizationTest`
- `AdminTenantApiTest`
- `AdminSubscriptionManagementTest`
- `AdminDeviceManagementTest`
- `AdminSubscriptionPlanManagementTest`
- `AdminAuditLogTest`
- `AdminNoRegressionBusinessApiTest`

Full backend suite: **250 tests, 856 assertions, all passing.**

## Backend Compatibility Evidence

All Sprint 1–10 endpoints remain intact (auth, tenant-context, sync, sales,
cash/QRIS payments, receipt, inventory, reports, closings, subscription status,
devices). Verified via `php artisan route:list` and the full regression suite.

## Validation Commands

```bash
bash scripts/sprint11_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd backend && php artisan route:list | grep -E "api/v1/admin" && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- Backend tests: PASS (250/250).
- composer validate --strict: PASS.
- Admin routes registered (13 endpoints): PASS.
- Sprint 11 smoke: PASS (structural).
- Android assembleDebug / testDebugUnitTest: run in Sprint 11 CI (authoritative
  build gate; local JDK toolchain limitation documented in project memory).

## GO Criteria

1. Foundation remains source of truth.
2. Sprint 0–11 rules present in `docs/PROJECT_RULES.md`.
3. Platform admin authorization available.
4. Tenant users cannot access admin APIs.
5. Admin route group under `/api/v1/admin`.
6. Admin tenant list/detail API available.
7. Admin subscription assign/update API available.
8. Admin device list/revoke API available.
9. Admin plan list/create/update/deactivate API available.
10. Admin audit log table/model/service available.
11. Admin mutations audit-logged.
12. Audit logs store no secrets/raw gateway payloads.
13. No real billing charge collection.
14. No tenant hard delete.
15. No impersonation.
16. No Android admin panel.
17. Sprint 10 subscription/device enforcement intact.
18. Sprint 2–10 business behavior intact.
19. Sprint 11 smoke pass.
20. Backend tests pass.
21. Android assembleDebug pass in CI.
22. Android testDebugUnitTest pass in CI.
23. Android secret scan pass.
24. No forbidden files committed.
25. PR/merge complete.
26. GO tag created and exact-match to main HEAD.

## No-Go Checks

Failure in any of: foundation/rules unreadable; Sprint 0–10 rules missing;
Sprint 11 rule missing; no platform admin authorization; tenant/unauthenticated
reaching admin APIs; no audit log; unlogged mutations; secrets in audit logs;
tenant hard delete; impersonation; real billing; Android admin panel added;
Sprint 10 enforcement broken; business regression; Android CI not running
assembleDebug/testDebugUnitTest or failing; backend tests fail; smoke fails;
wrong Android package/SDK levels; forbidden files committed; dirty tree.

## Follow-up for Sprint 12

Sprint 12 — Tenant Onboarding & Demo Data Foundation.
