# Sprint 34 — Android Runtime Hardening Evidence

Sprint: **Android Offline, Sync, Device Activation & Cashier Runtime Hardening**
Base: `main` @ Sprint 33 merge `9c247f1` (GO tag
`sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning-go`).

## Rule contract (ADR-R001..ADR-R030)

The canonical rules live in `config/android_runtime_governance.php`, are mirrored
in `config/pos_foundation.php` (`android_runtime_rules_sprint_34`) and
`docs/PROJECT_RULES.md`, and are locked by `android-runtime:governance-audit` +
CI grep.

- `ADR-R001` Android runtime access via the canonical `AndroidRuntimeAccessService`.
- `ADR-R002` Device activation via `DeviceActivationService`.
- `ADR-R003` Activation token hashed/non-reversible, never returned after creation.
- `ADR-R004` Activation idempotent per tenant/register/device fingerprint.
- `ADR-R005` Activation respects the Sprint 32 device/register limits.
- `ADR-R006` Activation fails closed for an unknown tenant/register/plan.
- `ADR-R007` Manual suspension blocks Android writes regardless of billing state.
- `ADR-R008` Unpaid past grace blocks writes / forces read-only per governance.
- `ADR-R009` Trial expired blocks writes / forces read-only per governance.
- `ADR-R010` Cashier session validates tenant/branch/register/device/role/entitlement.
- `ADR-R011` Cashier runtime denials are audit-logged.
- `ADR-R012` Offline sales/orders carry a client UUID/idempotency key.
- `ADR-R013` Server rejects a duplicate client UUID without a duplicate mutation.
- `ADR-R014` Sync batch is idempotent and retry-safe.
- `ADR-R015` A failed sync item is retryable without duplicating a sale/order.
- `ADR-R016` Conflict policy is deterministic and explainable.
- `ADR-R017` Catalog/settings sync is tenant-isolated.
- `ADR-R018` Stock/price/customer/payment snapshots do not leak other tenants.
- `ADR-R019` Offline queue has a bounded size and age.
- `ADR-R020` Android local storage avoids raw secrets/PII where possible.
- `ADR-R021` Android logs do not leak tokens/passwords/PII.
- `ADR-R022` Sync API output is redacted and safe.
- `ADR-R023` Settlement state only from the Sprint 30/31 trusted services.
- `ADR-R024` Android never marks an invoice paid or unlocks entitlement locally.
- `ADR-R025` Entitlement refresh fails safe when stale.
- `ADR-R026` Device revoke/disable blocks future sync/write.
- `ADR-R027` Register/device mismatch is denied and audited.
- `ADR-R028` Platform-admin/device-support bypass is explicit and audited.
- `ADR-R029` Prior Sprint 24–33 gates remain green.
- `ADR-R030` Go/no-go verifies activation, offline queue, sync idempotency, cashier runtime, entitlement, redaction.

## Backend evidence

- Migrations: `tenant_device_activations`, `tenant_android_sync_batches`, `tenant_android_sync_items`.
- Models: `TenantDeviceActivation`, `TenantAndroidSyncBatch`, `TenantAndroidSyncItem`.
- Services (`App\Services\AndroidRuntime`): `AndroidRuntimeAccessService`,
  `DeviceActivationService`, `DeviceRevocationService`, `CashierRuntimeSessionService`,
  `AndroidOfflinePolicyService`, `AndroidSyncIngestionService`, `AndroidSyncConflictService`,
  `AndroidSyncRedactor`, `AndroidRuntimeSummaryService`, `AndroidRuntimeGovernanceAuditService`,
  `AndroidRuntimeGoNoGoService`, `AndroidRuntimeAuditService`, `AndroidRuntimeSimulator`,
  `AndroidRuntimeDecision`, `AndroidSyncBatchData`, `AndroidRuntimeException`.
- Controllers: `Api/V1/Android/{DeviceActivationController, AndroidRuntimePolicyController,
  AndroidSyncController, CashierRuntimeSessionController}`, `Api/V1/Admin/AdminAndroidRuntimeController`.
- Requests: `Android/{ActivateDeviceRequest, SyncBatchRequest}`.
- Commands: `android-runtime:{device-summary, activation-simulate, sync-summary, sync-simulate,
  cashier-check, governance-audit, go-no-go}`.

## Backend tests

Full suite: **1226 passed, 32116 assertions, 0 failures** (sqlite).
Sprint 34 focused suites (`--filter Android`, 59 tests):

- `AndroidRuntimeGovernanceTest` — ADR rules present in config/foundation/PROJECT_RULES;
  guardrails locked false; runtime fail-closed; governance-audit no FAIL; go-no-go not NO_GO.
- `AndroidDeviceActivationServiceTest` — valid activation; idempotent per fingerprint;
  token hashed + never exposed; expired/mismatch/suspension denied; no-unlimited fallback;
  over-device-limit denied; revocation blocks future sync + audited.
- `AndroidCashierRuntimeTest` — valid cashier allowed; non-operator/wrong-tenant denied;
  manual suspension wins + denial audited.
- `AndroidSyncIngestionTest` — valid accepted; replay idempotent; duplicate item no
  double-mutation; **real sale sync idempotent (one Sale for one client UUID)**; revoked
  device / suspended tenant denied with deterministic conflict; payment item skipped
  (server-only); batch output redacted.
- `AndroidRuntimeApiTest` — activation route returns no token; policy readable; sync
  idempotent via API; admin routes require platform.admin; revoke audited.
- `AndroidRuntimeCommandsTest` — all commands run; activation-simulate idempotent;
  all 8 sync-simulate scenarios assert their invariant.

## Android tests

Pure-JVM unit tests (run under `testDebugUnitTest`, JDK 21 on CI):

- `AndroidRuntimeStateTest` — allowed permits writes; blocked/read-only deny; **stale
  snapshot fails safe to read-only**; reason→message mapping; lenient wire parsing.
- `DeviceActivationRequestTest` — DTO carries the token to the wire but `toString()`/
  `redactedForLog()` never leak the token or fingerprint.
- `OfflineSyncBatchFactoryTest` — every item carries its stable client id; retrying the
  same set is idempotent (same batch id); different set → different id; empty rejected.

## Smoke

`scripts/sprint34_smoke.sh` — structural + config/guardrail + command gates on an
isolated sqlite file, deterministic activation/sync probes (idempotency, replay,
duplicate, conflict, revoked-device, suspended/unpaid/trial fail-closed), audit-trail
probe, no-secret/PII assertion, and prior Sprint 24–33 gate replays.

Result: **PASS (0 FAIL).**

## Go / No-Go

`php artisan android-runtime:go-no-go --strict` → **GO**. Aggregates the governance
audit, the Sprint 34 command self-contract, the Sprint 24–33 prior-gate contract, the
runtime-service wiring, and the full commercial-chain compatibility.

## CI

`.github/workflows/sprint34-ci.yml` — backend tests (PHP 8.5, sqlite) + Sprint 24–33
prior gates + governance-audit + go-no-go + smoke + ADR grep in config/foundation/docs,
and Android build + unit tests (JDK 21).

## Regression

Sprint 24–33 gates re-run green in smoke and CI; no Sprint 23 `saas_billing_*`, Sprint
30 collection, Sprint 31 settlement, Sprint 32 entitlement or Sprint 33 onboarding
behaviour is modified. Additive tables/services/routes/commands only.

## Rollback

See the architecture doc's Rollback section. Additive-only; drop the three migrations
and remove the new namespace/commands/routes/config to fully revert.

## Deferred risks

- Sale sync supports `create`; update/void of a synced sale deferred (no server void domain yet).
- Catalog-version / price-snapshot conflict negotiation wired + explainable; full
  version negotiation deferred.
