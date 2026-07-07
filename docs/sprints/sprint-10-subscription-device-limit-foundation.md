# Sprint 10 — Subscription & Device Limit Foundation

## Objective

Establish the commercial SaaS foundation:

```
Tenant → Subscription Plan → Active Subscription → Registered Devices → Device Limit Enforcement → Android Status UI
```

Subscription and device access are tenant-owned, backend-enforced, and
Android-aware. No real billing is charged in this sprint — only the plan/status/
device-limit foundation.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (sections 8, 9, 10, 12, 16, 17, 21, 22, 25, 26)
- `docs/PROJECT_RULES.md`

## Previous Sprint Foundation Lock

Sprints 0–9 remain the governing lock (see `docs/PROJECT_RULES.md` → Foundation
Lock Index, now extended with this document at index 12). All prior runtime
rules stay in force; none are removed.

## Scope

In scope: subscription plans, tenant subscriptions, registered devices, backend
subscription-status resolution, device registration/heartbeat/list/revoke, device
limit enforcement, subscription + device middleware on protected business APIs,
Android device identity, device registration/status repositories, a lightweight
subscription status screen, and login/session integration.

Out of scope (No-Go): real billing charge collection, invoice billing, Play/Apple
billing, proration, reseller/self-service billing portal, advanced billing
analytics, invasive hardware fingerprinting.

## Graphify Summary

- Reused Sprint 1 `TenantContext` + `SetTenantContext`/`EnsureTenantIsActive`
  middleware; the new `subscription.active` and `device.registered` middleware run
  after tenant context inside the authenticated group.
- Reused the Sprint 4–9 business route group and wrapped it with the two new
  middleware; auth, subscription status, and device management endpoints stay
  outside the gate so a blocked tenant can still read status and register/revoke.
- Reused Android `ServiceLocator`/`ApiClient`/`AuthInterceptor` patterns; added a
  parallel `DeviceHeaderInterceptor` and a locally generated device identity.

## Backend Implementation

### Subscription Plans

- Migration `create_subscription_plans_table` — `code` (unique), `name`,
  `price_monthly`, `max_stores`, `max_devices`, `max_products?`, `features?`,
  `is_active`.
- `App\Models\SubscriptionPlan` with plan-code constants (`lite`/`starter`/`pro`).
- `SubscriptionPlanSeeder` idempotently seeds the three plans
  (lite 1/1, starter 1/3, pro 3/10). Prices are placeholders.

### Tenant Subscriptions

- Migration `create_tenant_subscriptions_table` — `tenant_id`,
  `subscription_plan_id`, `status`, `starts_at`, `ends_at?`, `trial_ends_at?`,
  `grace_ends_at?`, `cancelled_at?`, `suspended_at?`, `metadata?` with indexes on
  tenant/status/date columns.
- `App\Models\TenantSubscription` with status constants TRIAL/ACTIVE/GRACE/
  EXPIRED/CANCELLED/SUSPENDED.

### Registered Devices

- Migration `create_registered_devices_table` — `tenant_id`, `user_id?`,
  `store_id?`, `device_uuid`, `device_name?`, `platform` (default ANDROID),
  `app_version?`, `last_seen_at?`, `registered_at`, `revoked_at?`, `status`,
  `metadata?`. Unique `(tenant_id, device_uuid)`; indexes on tenant/status.
- `App\Models\RegisteredDevice` with status constants ACTIVE/REVOKED/BLOCKED and
  the `ANDROID` platform constant.
- `Tenant` gains `tenantSubscriptions()`, `registeredDevices()`,
  `currentSubscription()`, `activeRegisteredDevices()`.

### Subscription Status Rules

`SubscriptionStatusService::resolve(Tenant)` returns an immutable
`SubscriptionStatus` (allowed, status, reason, plan, subscription). The decision
is recomputed from the date columns — never trusted from the client:

- TRIAL allowed until `trial_ends_at`.
- ACTIVE allowed until `ends_at` (falls back into an open grace window if set).
- GRACE allowed until `grace_ends_at`.
- EXPIRED / CANCELLED / SUSPENDED blocked.
- Missing subscription blocked.

### Device Limit Rules

`DeviceRegistrationService`:

- Registration requires an allowed subscription.
- Active devices count against `plan.max_devices`; revoked/blocked devices do not.
- Re-registering an already-active `(tenant, device_uuid)` replays it without
  consuming a second slot.
- Registering a revoked device is rejected (`DEVICE_REVOKED`); over-limit is
  `DEVICE_LIMIT_REACHED`.
- Heartbeat refreshes `last_seen_at` for a tenant-owned, non-revoked device.
- Revoke sets `status=REVOKED` + `revoked_at` and frees the slot.

### Middleware Enforcement

- `EnsureTenantSubscriptionIsActive` (`subscription.active`) → 402 with
  `SUBSCRIPTION_INACTIVE` / `SUBSCRIPTION_NONE` when blocked.
- `EnsureDeviceIsRegistered` (`device.registered`) → 403 `DEVICE_NOT_REGISTERED`
  (missing/unknown `X-Device-UUID`) or `DEVICE_REVOKED`.
- Applied to the protected business group (products, sync, sales, receipt, qris,
  payments, inventory, reports, closings). Excluded: health, auth login/logout/me,
  tenant-context, subscription status, and device management endpoints.

### API Routes (`/api/v1`)

```
GET  /subscription/status        (auth + tenant, ungated)
POST /devices/register           (auth + tenant, ungated)
POST /devices/heartbeat          (auth + tenant, ungated)
GET  /devices                    (auth + tenant, ungated)
POST /devices/{device}/revoke    (auth + tenant, ungated)
<all Sprint 2–9 business routes>  (auth + tenant + subscription.active + device.registered)
```

### Tenant Isolation Rules

Tenant A can never view, register, revoke, or consume device slots from tenant B.
`store_id` on registration must belong to the acting tenant. The same
`device_uuid` under two tenants does not collide (unique is per tenant).

## Android Implementation

### Android Device Identity

- `core/device/DeviceIdentityStore` — generates a UUID once and persists it in
  SharedPreferences (`aish_pos_device`). Contains no password / gateway secret /
  invasive fingerprint. Exposes `DeviceUuidProvider` for the interceptor.
- `core/device/DeviceInfoProvider` — optional display name (Build manufacturer/
  model) + app version.
- `core/network/DeviceHeaderInterceptor` — attaches `X-Device-UUID` once an
  identity exists; never before, never logged.

### Android Device Registration

- DTOs: `SubscriptionDtos.kt`, `DeviceDtos.kt`.
- `SubscriptionRepository.getStatus()`, `DeviceRepository.registerCurrentDevice()/
  heartbeat()/listDevices()` — return `ResultState`; never fake an allowed state.
- `PosApiService` gains the 5 subscription/device endpoints; `ApiClient` wires the
  device header interceptor; `ServiceLocator` exposes the new singletons.

### Android Subscription Status UI

- `feature/subscription/SubscriptionStatusDisplay` — pure mapping (allowed/blocked,
  plan/device labels, device-limit message).
- `SubscriptionStatusViewModel` + `SubscriptionStatusActivity` +
  `activity_subscription_status.xml` — lightweight status/retry/logout screen. No
  billing/upgrade/Play Billing UI.
- Login/session flow: after auth, the app ensures a device UUID, reads
  subscription status, and registers the device. Success → Cashier; a blocked
  subscription or rejected device → blocked state + status screen; network error →
  clear error (never a faked allowed state). A best-effort device heartbeat runs on
  cashier start.

### Android Build CI Evidence

`.github/workflows/sprint10-ci.yml` job `android-build-test` runs `:app:assembleDebug`
and `:app:testDebugUnitTest` on JDK 21 (no `continue-on-error`). Local build is not
possible on this workstation (JDK 25, no Android SDK), so CI is the authoritative
Android build/test gate (as since Sprint 6).

## Application Rules Update

`docs/PROJECT_RULES.md` — Foundation Lock Index extended to index 12; new
"Sprint 10 Subscription & Device Limit Foundation Runtime Rule" appended (18
mandatory rules). `backend/config/pos_foundation.php` — added `sprint_10` and the
subscription/device rule flags.

## Testing Evidence

Backend (`php artisan test`): **211 passed, 707 assertions** (182 prior + 29 new).
New Sprint 10 feature tests:

- `SubscriptionStatusApiTest` — active/trial/grace allowed; expired/cancelled/
  suspended/missing blocked; plan limits + device count present.
- `DeviceRegistrationApiTest` — register, duplicate replay, heartbeat, revoked
  heartbeat rejected, foreign store rejected.
- `DeviceLimitEnforcementTest` — over-limit 403, revoked frees a slot, duplicate
  active does not consume a slot.
- `SubscriptionMiddlewareTest` — active allowed; expired blocks inventory/reports
  (402); status + login not blocked.
- `DeviceMiddlewareTest` — active device allowed; missing header / revoked blocked;
  registration not blocked by missing device.
- `SubscriptionTenantIsolationTest` — no cross-tenant list/revoke/register/slot
  consumption; identical UUID does not collide.

Existing Sprint 2–9 suites stay green because `TenantFactory` auto-provisions an
ACTIVE Starter subscription + one ACTIVE device (`test-device-uuid`), and the base
`TestCase` sends the matching `X-Device-UUID` header.

Android unit tests (run in CI): `DeviceIdentityStoreTest`,
`SubscriptionStatusMappingTest`, `DeviceHeaderInterceptorTest`,
`DeviceRegistrationFlowTest`. The 5 existing `PosApiService` fakes were extended
with the new endpoint overrides.

## Backend Compatibility Evidence

All Sprint 1–9 endpoints remain registered (see the CI route-compatibility step).
Behavior of cash, QRIS, receipt, printer, offline sync, inventory, reports, and
closing is unchanged; those routes are now additionally gated by an allowed
subscription + registered device, which their test tenants satisfy by default.

## Validation Commands

```bash
bash scripts/sprint10_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- `sprint10_smoke.sh`: pass (structural).
- `composer validate --strict`: valid.
- `php artisan test`: 211 passed.
- Android build/test: authoritative via `sprint10-ci` (JDK 21).

## GO Criteria

See section 19 of the sprint brief — all backend criteria met locally; Android
assembleDebug/testDebugUnitTest verified green in `sprint10-ci` before tagging.

## No-Go Checks

None triggered: rules intact, tables/models/services/middleware present, device
limit enforced, expired subscription blocks business APIs, no cross-tenant access,
no password/gateway secret in device identity, no real billing implemented, prior
sprint behavior intact, package `com.aishtech.poslite`, minSdk 26 / targetSdk 35.

## Follow-up for Sprint 11

- Real subscription billing (invoice + charge collection) as a dedicated sprint.
- Admin device-management UI and remote revoke notifications.
- Grace-period messaging and renewal reminders on Android.
