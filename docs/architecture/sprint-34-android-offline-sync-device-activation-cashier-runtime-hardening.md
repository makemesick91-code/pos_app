# Sprint 34 — Android Offline, Sync, Device Activation & Cashier Runtime Hardening

## Scope

Make the Android POS runtime safe for real UMKM field use, additively and without
weakening any Sprint 23–33 semantics:

- Device/register **activation** is governed, idempotent, auditable and tenant-isolated.
- **Cashier runtime session** is validated against tenant/branch/register/device/role and entitlement.
- Android handles unstable internet gracefully via a **bounded, deterministic offline queue**.
- **Sync** never double-submits sales/orders — idempotent by client UUID and batch id.
- The backend **rejects** invalid/duplicate/out-of-entitlement device/cashier/sync actions and **fails closed**.

## Non-goals

- No large Android UI rewrite; the existing Sprint 7 offline queue (`offline_sales`,
  `clientReference` idempotency) is reused, not replaced.
- No new heavy Android dependency (Room/WorkManager/Retrofit already present).
- No external network / real QRIS / payment-gateway dependency in CI.
- No VPS deploy.
- Android never decides settlement or entitlement; it is UX only.

## Commercial SaaS chain

Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement
Runtime Access → Tenant Onboarding → **Android Runtime**.

The Android runtime is the last link: it consumes the trusted server-side billing/
entitlement/onboarding state and never mutates it.

## Android runtime architecture

Every Android runtime write (activation, cashier session, sync) is gated by the
single canonical `App\Services\AndroidRuntime\AndroidRuntimeAccessService`, which:

1. delegates the billing/subscription/lifecycle write dimension to the Sprint 32
   `EntitlementAccessService` (`canWrite`, `canRegisterDevice`) — never re-implemented;
2. maps a denied entitlement decision to a deterministic Android runtime decision
   (`allowed` / `degraded` / `read_only` / `blocked` / `denied`) plus a stable
   sync conflict code and HTTP status;
3. enforces the dimensions entitlement does not cover: device activation usability,
   register/device/tenant consistency, and cashier role.

Precedence (fail closed): manual suspension (423) → unpaid-past-grace (402/blocked)
→ trial-expired (read-only) → unknown plan (fail closed, never unlimited) →
over-limit (429) → active.

## Device activation model

`DeviceActivationService` is the only activation path (ADR-R002).

- `prepare()` mints a one-time raw token, stores only its **sha256 hash**, and
  returns the raw token exactly once for out-of-band hand-off.
- `activate(token, fingerprint, deviceUuid?, label?)` verifies the token by hash,
  is **idempotent per (tenant, fingerprint)** (one `RegisteredDevice`), runs the
  Sprint 32 device-limit + billing gate, and never stores/returns the raw token.
- A pre-existing Sprint 10 `registered_devices` row is bridged to a Sprint 34
  activation via `resolveForDevice()`.

### Token hash / fingerprint model

`activation_token_hash = sha256(token)`, `device_fingerprint_hash = sha256(fingerprint)`.
Neither the raw token nor the raw fingerprint is ever persisted, logged, or returned
(`toSafeArray()` omits both). Guardrails `raw_activation_token_stored_allowed` and
`raw_activation_token_returned_after_creation_allowed` are locked `false`.

### Device revocation model

`DeviceRevocationService` (platform-admin only, audited) moves the activation to
`revoked` and the paired `RegisteredDevice` to `REVOKED`, so both the Sprint 34
sync gate and the Sprint 10 `device.registered` gate reject it thereafter (ADR-R026).

## Cashier runtime / session model

There is no separate session table; a "session" is a validated runtime posture
(`CashierRuntimeSessionService`): role ∈ operator roles, tenant match, register/
device consistency, and the billing write state. A denied attempt is audit-logged
to `admin_audit_logs` with redacted metadata (ADR-R010/R011).

## Offline queue model

The existing Sprint 7 `offline_sales` queue already carries a device-generated
`clientReference` UUID with unique-index idempotency and `PENDING/SYNCING/SYNCED/
FAILED/CONFLICT` retry states. Sprint 34 adds the client-side
`OfflineSyncBatchFactory` (deterministic batch id from the ordered item ids so a
retry replays), `AndroidRuntimePosture` (write-allowed/read-only, fail-safe on
stale), and bounded queue policy (`queue_max_items`, `queue_max_age_hours`).

## Sync idempotency model

`AndroidSyncIngestionService` defends idempotency at two levels:

- **Batch**: a replayed `(tenant, client_batch_id)` or `idempotency_key` resumes the
  stored `tenant_android_sync_batches` row and never re-mutates (ADR-R014).
- **Item**: a `client_item_id` already `accepted` for the tenant is recorded a
  `duplicate` with no second mutation (ADR-R013). Sale items additionally reuse the
  Sprint 7 `SaleService` `client_reference` idempotency, so the POS domain service
  is never bypassed. Payment items are recorded `skipped` (server-only settlement,
  ADR-R023/R024).

## Conflict policy

Server-authoritative and deterministic (`AndroidSyncConflictService`). Stable codes
from `config/android_runtime_governance.php`: `duplicate_client_item`,
`stale_catalog_version`, `stale_price_snapshot`, `register_mismatch`,
`device_revoked`, `tenant_read_only`, `tenant_suspended`, `unpaid_past_grace`,
`trial_expired`, `entitlement_denied`, `invalid_payload`.

## Suspended / read-only / unpaid / trial behaviour

`config android_runtime_governance.runtime_behavior`: suspended → `block`,
unpaid-past-grace → `block`, trial-expired → `read_only`, stale snapshot →
`read_only` (fail safe). Manual suspension always wins; a paid invoice never lifts
it and Android can never mark an invoice paid or unlock entitlement locally.

## Entitlement (Sprint 32) & onboarding (Sprint 33) integration

The runtime gate calls `EntitlementAccessService` only; failed/cancelled/expired
gateway events never unlock (they flow through the trusted Sprint 30/31 collection
layer). Onboarding-provisioned register/device setup becomes usable via activation
without weakening any onboarding/provisioning state.

## Backend API route matrix

| Method | Route | Guard | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/android/device/activate` | auth + tenant.context | Idempotent activation (no token echo) |
| GET | `/api/v1/android/runtime/policy` | auth + tenant.context | Safe offline/runtime policy |
| POST | `/api/v1/android/device/heartbeat` | + device.registered | Update last-seen |
| GET | `/api/v1/android/cashier/session` | + device.registered | Cashier runtime posture |
| POST | `/api/v1/android/cashier/session/start` | + device.registered | Validate session |
| POST | `/api/v1/android/cashier/session/end` | + device.registered | Client-side clear |
| POST | `/api/v1/android/sync/batch` | + device.registered | Idempotent sync batch |
| GET | `/api/v1/android/sync/batch/{clientBatchId}` | + device.registered | Read batch result |

## Admin route matrix (platform.admin)

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/v1/admin/android-runtime/devices` | List activations + summary |
| GET | `/api/v1/admin/android-runtime/devices/{activation}` | Activation detail |
| POST | `/api/v1/admin/android-runtime/devices/{activation}/revoke` | Revoke (audited) |
| GET | `/api/v1/admin/android-runtime/sync-batches` | List batches + summary |
| GET | `/api/v1/admin/android-runtime/sync-batches/{batch}` | Batch + items |
| GET | `/api/v1/admin/android-runtime/conflicts` | Recent conflicts |
| GET | `/api/v1/admin/android-runtime/governance` | Governance signals |

## Command matrix

| Command | Purpose |
| --- | --- |
| `android-runtime:device-summary` | Safe device activation summary |
| `android-runtime:activation-simulate` | Idempotent activation probe (dry-run default) |
| `android-runtime:sync-summary` | Safe sync batch/item summary |
| `android-runtime:sync-simulate` | Deterministic sync scenario runner |
| `android-runtime:cashier-check` | Dry-run cashier runtime posture |
| `android-runtime:governance-audit` | ADR-R001..R030 config/guardrail audit |
| `android-runtime:go-no-go` | Hard Sprint 34 GO/WATCH/NO-GO gate |

## Android implementation notes

- `core/runtime/AndroidRuntimeState.kt` — `RuntimeStatus`, `AndroidRuntimePosture`
  (write-allowed/read-only, fail-safe), `AndroidRuntimeMessages` (friendly, PII-safe).
- `core/runtime/DeviceActivationRequestFactory.kt` — builds the wire request;
  `DeviceActivationInput.toString()` always redacts the token/fingerprint.
- `feature/sync/OfflineSyncBatchFactory.kt` — deterministic, idempotent batch build.
- `data/remote/dto/AndroidRuntimeDtos.kt` + `core/network/PosApiService.kt` — endpoints.

## Data model

- `tenant_device_activations` — activation lifecycle, hashed token, fingerprint hash,
  device link, redacted metadata.
- `tenant_android_sync_batches` — batch idempotency ledger + counts.
- `tenant_android_sync_items` — per-item result trail (unique `(batch, client_item_id)`).

## Dependency graph

`AndroidRuntimeAccessService` → `EntitlementAccessService` (S32) → billing/plan (S26/S30/S31).
`AndroidSyncIngestionService` → `SaleService` (S7 idempotency). `DeviceActivationService`
→ `RegisteredDevice` (S10). Activation optionally links `tenant_provisioning_runs` (S33).

## Rollback

Additive only. To roll back: drop the three Sprint 34 migrations (they have `down()`),
delete `config/android_runtime_governance.php`, the `App\Services\AndroidRuntime`
namespace, the `android-runtime:*` commands, the Android `android/*` routes and
`Api/V1/Android` controllers, and revert the `routes/api.php` / `pos_foundation.php`
/ `PROJECT_RULES.md` additions. No Sprint 23–33 table or behaviour is modified.

## Tests / CI / smoke evidence

See `docs/sprints/sprint-34-android-runtime-hardening-evidence.md`.

## Deferred risks

- Sale-item sync currently supports `create`; update/void of a synced sale is a
  future sprint (order/void domain does not yet exist server-side).
- `stale_catalog_version` / `stale_price_snapshot` conflict codes are wired and
  explainable but catalog-version negotiation is a future enhancement.
