# UIX-8C-07 — Backend Discovery (Auth, Device Activation, Settings & Session Recovery)

**Scope:** DISCOVERY ONLY. No source changed. Backend root: `backend/` (Laravel 12, PHP 8.5).
DB: sqlite in-memory for tests; PostgreSQL `aish_pos_pilot` on VPS. Auth: Laravel Sanctum (personal access tokens).

---

## 1. Authentication (tenant / Android API)

- **Sanctum**: `HasApiTokens` on `User`; tokens in `personal_access_tokens`
  (migration `2026_07_06_222613_create_personal_access_tokens_table.php`, standard Sanctum shape incl. `abilities`, `last_used_at`, `expires_at`). Guard `auth:sanctum`.
- **Login route**: `POST /api/v1/auth/login` → `App\Http\Controllers\Api\V1\AuthController::login(LoginRequest)`.
  - `App\Http\Requests\Api\V1\LoginRequest` rules: `email => required|email`, `password => required|string`.
  - Verifies `Hash::check`, rejects `! $user->is_active` (403), then `$token = $user->createToken('api')->plainTextToken` — **no abilities, no expires_at set** (so tokens do not expire unless `config/sanctum` sets a default; default is null → non-expiring).
  - **Response JSON**: `{ token, token_type:"Bearer", user:{id,name,email,role,tenant_id,store_id}, tenant:{id,name,status}|null, store:{id,name,code}|null }`.
- **Logout**: `POST /api/v1/auth/logout` (auth) → deletes `currentAccessToken()`. Returns `{success:true}`.
- **Cashier auth**: identical login flow. `User.role = 'cashier'` (`User::ROLE_CASHIER`). No separate cashier login endpoint — a cashier is just a `User` with `role=cashier`, `is_active=true`, and a `tenant_id` (+ optional `store_id`).
- **User model** (`app/Models/User.php`): roles `ROLE_SAAS_ADMIN='saas_admin'`, `ROLE_TENANT_OWNER='tenant_owner'`, `ROLE_STORE_ADMIN='store_admin'`, `ROLE_CASHIER='cashier'`. Fillable incl. `tenant_id`, `role`, `is_active`, `store_id`, `platform_admin_revoked_at`. Casts: `is_active=>boolean`. Helpers: `isCashier()`, `isTenantOwner()`, `isSaasAdmin()`, `isPlatformAdmin()`, `belongsToTenant(?int)`, scopes `active()`, `forTenant(int)`. Relations `tenant()`, `store()`.

---

## 2. Device Activation (Sprint 34 domain)

### Endpoints
- `POST /api/v1/android/device/activate` → `App\Http\Controllers\Api\V1\Android\DeviceActivationController::activate(ActivateDeviceRequest)`
  (route alias in api.php: imported as `AndroidDeviceActivationController`). Group: `auth:sanctum` + `tenant.active` + `tenant.context` — **NOT** behind `device.registered` (device not yet registered).
  - `App\Http\Requests\Api\V1\Android\ActivateDeviceRequest` rules: `activation_token => required|string|min:8|max:128`, `device_fingerprint => required|string|min:8|max:191`, `device_uuid => nullable|string|max:191`, `device_label => nullable|string|max:120`, `store_id => nullable|integer`, `register_id => nullable|integer`.
  - Tenant is **server-authoritative**: `$this->context->tenant()` (from `TenantContext`, never request body).
  - Calls `DeviceActivationService::activate(tenant, rawToken, fingerprint, deviceUuid, label, actor)`.
  - On `AndroidRuntimeException` returns `{message, code}` with `$e->status`. Success: `{ data: activation.toSafeArray(), meta:{foundation} }`, HTTP 200.
- `POST /api/v1/android/device/heartbeat` → same controller `::heartbeat` (group `device.registered`). Reads `X-Device-UUID` header, finds `RegisteredDevice`, `resolveForDevice()` + `heartbeat()` (updates `last_seen_at`), returns `activation.toSafeArray()`. **This is the closest thing to a validity poll** — but a revoked device is blocked by `device.registered` (403) *before* the controller.
- `GET /api/v1/android/runtime/policy` → `AndroidRuntimePolicyController::show` (`AndroidOfflinePolicyService::policyFor(tenant,user)`). Read-only offline policy; no per-device validity status.

### Service — `App\Services\AndroidRuntime\DeviceActivationService`
- `activate(Tenant, string $rawToken, string $fingerprint, ?string $deviceUuid=null, ?string $label=null, ?User $actor=null): TenantDeviceActivation`
  - Idempotent per `(tenant, device_fingerprint_hash)` where status = ACTIVATED → returns existing, bumps `last_seen_at`.
  - Matches a PENDING row by `activation_token_hash`; if none and `activation_token.allow_auto_prepare=true` (config default **true**), it **auto-creates** a pending activation from the presented token — i.e. **there is currently no external issuance step required**; the token is effectively self-asserted then hashed.
  - Rejects expired (→ status EXPIRED), fingerprint mismatch (`REGISTER_MISMATCH`), entitlement (via `AndroidRuntimeAccessService::authorizeActivation`, fail-closed for suspended/over-limit).
  - In a `DB::transaction`: `updateOrCreate` a `RegisteredDevice` (`device_uuid` = provided or `act-<fpHash[0:40]>`, status ACTIVE, `revoked_at=null`), sets activation → ACTIVATED with `device_fingerprint_hash`, `activated_at`, `last_seen_at`. Never stores/returns raw token.
- `prepare(...)`: builds a PENDING activation with `activation_token_hash`, `expires_at = now + ttl`. **NO callers anywhere in app/routes/CLI** — the issuance/provisioning surface is not wired.
- `resolveForDevice(RegisteredDevice): TenantDeviceActivation` — finds/synthesizes an activation for an already-registered device (used by heartbeat).
- `heartbeat(activation)`: bumps `last_seen_at`. `hashToken`/`hashFingerprint`: `hash('sha256', ...)` (algo from config).

### Revocation — `App\Services\AndroidRuntime\DeviceRevocationService`
- `revoke(TenantDeviceActivation, User $actor, ?string $reason=null): TenantDeviceActivation` — idempotent (revoked → no-op). Sets activation `status=revoked`, `revoked_at=now`, `failure_reason='REVOKED: '.$reason`; moves paired `RegisteredDevice` → `status=REVOKED`, `revoked_at=now`. Audited via `AndroidRuntimeAuditService::recordAdminAction(actor, ACTION_DEVICE_REVOKED, activation, ['reason'=>$reason])`.
- **No dedicated `revocation_reason` column** — reason is stuffed into `failure_reason` as text.

### Model — `App\Models\TenantDeviceActivation`
- Statuses: `pending`, `activated`, `revoked`, `expired`, `failed`.
- Helpers: `isActivated()`, `isRevoked()`, `isExpired()` (status OR `expires_at->isPast()`), `isUsable()` (activated & !revoked & !expired), scope `forTenant(int)`.
- `toSafeArray()`: `{id, tenant_id, store_id, register_id, device_id, status, device_label, activated_at, revoked_at, expires_at, last_seen_at}` — token/fingerprint hashes deliberately excluded.

### Activation lifecycle summary
Currently **self-service / auto-prepare** (no admin-issued short-lived code path is wired). Token TTL config exists (`activation_token_ttl_minutes`, default 1440 = 24h) but only applies when `prepare()` or auto-prepare runs. There is a revoke path (platform-admin + support-ops), **no working reactivate** (support reactivate returns `supported:false`), and **no owner or CLI provisioning command** for devices.

---

## 3. Guard chain & server-side tenant/device context

- **Alias map** (`bootstrap/app.php`): `tenant.active`→`EnsureTenantIsActive`, `tenant.context`→`SetTenantContext`, `subscription.active`→`EnsureTenantSubscriptionIsActive`, `tenant.lifecycle`→`EnsureTenantLifecycleAllowed`, `tenant.entitled`→`EnsureTenantEntitled`, `tenant.usage.limit`→`EnsureTenantUsageLimitAvailable`, `device.registered`→`EnsureDeviceIsRegistered`, `platform.admin`→`EnsurePlatformAdmin`, plus `platform.admin.web`, `tenant.owner.web`.
- **Business chain order** (protected POS routes): `subscription.active` → `tenant.lifecycle` → `device.registered` → `entitlement.write` (+ `tenant.entitled:<feature>` / `tenant.usage.limit:<key>`). Suspension (423) wins first.
- **`SetTenantContext`**: hydrates per-request `App\Support\TenantContext` from the **authenticated user** only. Tenant = `user->tenant` always. Store = `user->store` default; optional `X-Store-ID` header may override **only** if that store belongs to the user's tenant and is active, else 403. Client can never select a foreign tenant/store.
- **`EnsureDeviceIsRegistered`**: reads `X-Device-UUID` header → looks up `RegisteredDevice::forTenant(tenantId)->where('device_uuid',…)` → requires `isActive()`; missing/inactive → **403** with a code (`DEVICE_NOT_REGISTERED` etc.). Device authority is derived from the header **matched against the authenticated tenant** — never trusted alone.
- **`AndroidRuntimeAccessService`** (canonical runtime gate): `authorizeWrite`, `authorizeActivation` (billing/lifecycle + plan device limit, fail-closed), `authorizeSync(tenant, activation, cashier)` — denies when `!activation->isUsable()` → `DEVICE_REVOKED`/`DEVICE_EXPIRED`/`DEVICE_NOT_ACTIVATED` (403, conflict code `device_revoked`), `authorizeCashierSession(...)` — validates activation tenant/store/register binding + usability. Delegates billing dimension to Sprint 32 `EntitlementAccessService`.

---

## 4. `auth/me` context endpoint (CashierContextPresenter source)

- `GET /api/v1/auth/me` (auth:sanctum) → `AuthController::me`.
- **Response**: `{ user:{id,name,email,role,tenant_id,store_id}, tenant:{id,name,status}|null, store:{id,name,code}|null, foundation:"POS_ANDROID_SAAS_FOUNDATION" }`.
- **Note / gap for context header**: `/me` returns tenant + store (= outlet) + user (= cashier), but **no device context** (no activation/device fields) and no explicit "outlet" vs "store" naming. Device/session context is only obtainable via `/android/device/heartbeat` (`toSafeArray`) or `/android/runtime/policy`.

---

## 5. Audit logging (device / security events)

- Canonical logger: `App\Services\Admin\AdminAuditLogger` — `::log(...)` writes to `admin_audit_logs`; `::sanitize()` redacts sensitive keys (password/secret/token). (Rule 50.)
- Android wrapper: `App\Services\AndroidRuntime\AndroidRuntimeAuditService` (ctor injects `AdminAuditLogger`). Actions: `ANDROID_DEVICE_ACTIVATED`, `ANDROID_DEVICE_REVOKED`, `ANDROID_DEVICE_ACTIVATION_DENIED`, `ANDROID_SYNC_REJECTED`, `ANDROID_CASHIER_DENIED`, `ANDROID_SUPPORT_BYPASS`. `recordAdminAction(actor, action, activation, meta)` and `recordBypass(...)` both call `adminAudit->log(...)`.
- **Gap**: successful device *activation* by the cashier is not necessarily audited through the admin logger in the activate() path (only revoke/deny are shown wiring through the audit service via admin/support flows); a security-grade activation audit trail on the self-service activate endpoint should be confirmed/added.

---

## 6. Existing tests & factories

- Feature tests: `tests/Feature/AuthApiTest.php`, `AndroidDeviceActivationServiceTest.php`, `AndroidRuntimeApiTest.php`, `AndroidRuntimeGovernanceTest.php`, `AndroidRuntimeCommandsTest.php`, `DeviceMiddlewareTest.php`, `DeviceRegistrationApiTest.php`, `DeviceLimitEnforcementTest.php`, `AdminDeviceManagementTest.php`, `AdminPlatformAuthorizationTest.php`, `Uix3AdminAuthTest.php`, `Uix4OwnerAuthTest.php`.
- **Base `tests/TestCase.php`**: `setUp` sends `X-Device-UUID = TenantFactory::AUTO_DEVICE_UUID` (`'test-device-uuid'`) on every request so the `device.registered` gate is satisfied by default.
- **`database/factories/TenantFactory.php`**: `afterCreating` auto-provisions an ACTIVE `TenantSubscription` (STARTER plan) + one ACTIVE `RegisteredDevice` (`device_uuid='test-device-uuid'`). `resetSubscriptionState()` clears it for gate tests.
- **`UserFactory`**: default `role=cashier`, `is_active=true`; states `->platformAdmin()`, inactive state (`is_active=false`). Also `RegisteredDeviceFactory`, `StoreFactory`, `TenantSubscriptionFactory`.

---

## 7. Migrations & columns (device / activation / session / token)

- `2026_07_06_222613_create_personal_access_tokens_table.php` — Sanctum (has `abilities`, `last_used_at`, `expires_at`; expiry unused).
- `2026_07_07_300002_create_registered_devices_table.php` — **registered_devices**: `id, tenant_id(FK), user_id(FK,null), store_id(FK,null), device_uuid(191), device_name(null), platform(20)='ANDROID', app_version(40,null), last_seen_at(null), registered_at, revoked_at(null), status(20)='ACTIVE', metadata(json,null)`. Unique `(tenant_id, device_uuid)`. Statuses ACTIVE/REVOKED/BLOCKED.
- `2026_08_12_990040_create_tenant_device_activations_table.php` — **tenant_device_activations**: `id, tenant_id(FK cascade), store_id(FK,null), register_id(unsignedBigInt,null), device_id(FK registered_devices,null), provisioning_run_id(FK,null), activation_status='pending', activation_token_hash(null), device_fingerprint_hash(null), device_label(null), attempt_count, activated_by_user_id(FK,null), activated_at(null), revoked_at(null), expires_at(null), last_seen_at(null), failure_reason(text,null), metadata_json(json,null)`. Unique `(tenant_id, activation_token_hash)`; indexes on `(tenant_id,device_fingerprint_hash,activation_status)`, `(tenant_id,register_id,activation_status)`, `last_seen_at`.
- `2026_09_01_996004_create_tenant_support_sessions_table.php` — Sprint 35 **support** sessions (not cashier runtime sessions).
- **No app_version / installation_id / revocation_reason column** on `tenant_device_activations`. No dedicated cashier-runtime-session table found (session governed by `CashierRuntimeSessionService`; cashier session endpoints `GET/POST /api/v1/android/cashier/session[/start|/end]`).
- Sanctum token expiry: `expires_at` column exists but is not populated; login sets no expiry → **tokens are non-expiring by default** (relevant to "session expiry / recovery").

---

## 8. Route-model binding / policies / middleware for revoke

- Admin API (`platform.admin`): `POST /api/v1/admin/android-runtime/devices/{activation}/revoke` → `AdminAndroidRuntimeController::revoke`; list/show `GET …/devices[/{activation}]`. Implicit binding on `{activation}` = `TenantDeviceActivation`.
- Support-ops (`platform.admin`): `POST /api/v1/admin/support-ops/tenants/{tenant}/devices/{activation}/revoke` → `AdminSupportDeviceController::revoke(SupportDeviceRevokeRequest{reason_code required, whitelisted})` via `SupportDeviceOperationsService::revoke`; `…/reactivate` → returns `supported:false` (**reactivation not implemented**).
- Owner web (`tenant.owner.web`, `owner` guard): `GET /owner/devices` + `GET /owner/devices/{device}` → `OwnerDeviceController::index/show` — **read-only** (`toSafeArray`), tenant-scoped, foreign id 404. No owner revoke/provision route.
- No dedicated Policy classes for devices; authorization is via middleware + tenant scoping inside services.

---

## GAP ANALYSIS — secure device activation + revocation + session validation

### EXISTS (reuse)
- Sanctum login (`/auth/login`) + `/auth/me` + `/auth/logout`; `LoginRequest`.
- `User` roles incl. `cashier`/`tenant_owner`, `is_active`, `tenant_id`, `store_id`.
- `tenant_device_activations` table with **hashed token** (`activation_token_hash`, sha256), **hashed fingerprint**, **expiry** (`expires_at`), **tenant binding**, **store/register binding columns**, **device label**, **last-seen** (`last_seen_at`), **revoke status** (`activation_status=revoked` + `revoked_at`), lifecycle statuses (pending/activated/revoked/expired/failed).
- `registered_devices` with `app_version`, `last_seen_at`, `status`, `revoked_at`.
- Activation endpoint `POST /android/device/activate` (Form Request validated, server-authoritative tenant, idempotent per fingerprint, response redacted via `toSafeArray`).
- `DeviceActivationService::activate/prepare/heartbeat/hashToken/hashFingerprint`; `DeviceRevocationService::revoke` (idempotent, transactional).
- Runtime validity enforcement on protected routes: `EnsureDeviceIsRegistered` (403) + `AndroidRuntimeAccessService::authorizeSync/authorizeCashierSession` (denies revoked/expired activation, `device_revoked`).
- Heartbeat endpoint returning activation `toSafeArray` (partial validity signal).
- Audit primitives: `AdminAuditLogger::log/sanitize` + `AndroidRuntimeAuditService` device actions.
- Admin/support revoke endpoints; owner read-only device console.
- Test harness: base `X-Device-UUID`, `TenantFactory` auto sub+device, `UserFactory()->platformAdmin()` / cashier default.

### MUST BE ADDED (or confirmed)
- **Provisioning surface to ISSUE a short-lived activation credential.** `prepare()` exists but has **no caller** — needs a minimal action on an existing surface (owner `/owner/devices` or platform-admin `/admin/*` / support-ops, or a CLI like the existing `*ProvisionCommand` pattern) that mints a one-time hashed code + `expires_at` and returns the raw code once. (Rule: minimal provisioning only; no new admin app / fleet management.)
- **Explicit single-use vs reusable policy.** Current auto-prepare makes activation effectively self-asserted; tighten to require a provisioned token and enforce single-use (or a documented reusable policy). Consider disabling `allow_auto_prepare` for the secure flow.
- **`app_version` / build capture on activation** and **`installation_id` (installation binding)** — not columns on `tenant_device_activations` today (only on `registered_devices.app_version`). Add reversible migration if activation must record them.
- **`revocation_reason` as a first-class column** (currently overloaded into `failure_reason` text).
- **A dedicated "is this device still valid / revoked?" poll endpoint** the Android app can call that returns a structured `{status, revoked, reason, expires_at}` **without** being hard-403'd by `device.registered` (heartbeat is gated so a revoked device gets an opaque 403, not a reason). e.g. `GET /api/v1/android/device/status` returning the activation safe-array + revoke reason.
- **Rate limiting** on `/android/device/activate` (throttle) — not currently applied.
- **Replay protection** on activation token beyond single-use hashing (nonce/attempt cap exists via `max_activation_attempts` + `attempt_count`, confirm enforcement).
- **Security audit of successful cashier activation** through `AndroidRuntimeAuditService` (activate path) — confirm/wire `ACTION_DEVICE_ACTIVATED` on self-service activate.
- **Session expiry / recovery contract**: Sanctum tokens are non-expiring; define expiry (config or per-token `expires_at`) and a documented 401 recovery flow, plus a cashier-runtime-session expiry surface for Settings ("session age/expiry"). No cashier-session table exists yet.
- **Working reactivate** (support reactivate returns unsupported) if reactivation is in scope.

### Provisioning surface recommendation (minimal)
- **Tenant-owner** `/owner/devices` (`OwnerDeviceController`, `owner` guard, already tenant-scoped) is the least-privilege place to add a **"provision activation code"** + **"revoke device"** action (POST routes on the existing `owner` group) — wiring `DeviceActivationService::prepare()` and `DeviceRevocationService::revoke()`. Alternatively reuse the **platform-admin support-ops** device group (already has revoke) for issuance, or a CLI mirroring `TenantOwnerProvisionCommand`/`PlatformAdminProvisionCommand`. Keep it read-mostly; no fleet management.
