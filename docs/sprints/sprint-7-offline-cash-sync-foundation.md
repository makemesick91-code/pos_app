# Sprint 7 — Offline Cash & Sync Foundation

## Objective

Establish a correct offline **CASH** transaction + sync foundation:

- Cash transactions may be rung up offline and stored locally.
- QRIS remains **online-only** and is never created offline.
- Offline sales are replayed to the backend when connectivity returns.
- The backend is **idempotent** so a retried offline submit never double-books a sale.

## Source of Truth

Canonical foundation: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
(especially sections 10, 12, 13, 14, 15, 16, 17, 21, 22, 25, 26).

## Previous Sprint Foundation Lock

Sprints 0–6 rules remain intact in `docs/PROJECT_RULES.md`. The Foundation Lock
Index now includes this document, and a new **Sprint 7 Offline Cash & Sync
Foundation Runtime Rule** section is appended (20 mandatory rules).

## Scope

**In scope (implemented):**

- Backend: `client_reference` / `client_created_at` / `synced_at` on `sales`,
  unique `(tenant_id, store_id, client_reference)`, `ANDROID_OFFLINE` source,
  idempotent offline submit, `meta.idempotent_replay`.
- Android: Room offline sale entities + DAOs, `OfflineSaleRepository`,
  `OfflineSalesSyncWorker` (WorkManager, network-constrained, exponential
  backoff), `OfflineSalesSyncScheduler`, `NetworkMonitor`, `QrisOnlineOnlyGuard`,
  cashier offline checkout + manual sync UI + offline draft receipt label.

**Out of scope (not implemented):** offline QRIS, real background printer queue,
inventory movement runtime, advanced reports, owner dashboard, full subscription
billing, full device management, complex conflict-resolution UI.

## Graphify Summary

- Offline is **CASH-only**; QRIS stays online (foundation §13/§14).
- Android snapshots the cart → local queue (PENDING) → WorkManager replays →
  backend dedupes by `client_reference` → local row marked SYNCED/CONFLICT/FAILED.
- Backend keeps every Sprint 4–6 invariant: recalculates totals, snapshots
  product name/price, stays tenant-isolated, receipt stays payment-aware.
- Android build authority is CI (JDK 21 + committed Gradle wrapper), unchanged
  since Sprint 6.

## Backend Implementation

- Migration `2026_07_07_000012_add_offline_sync_fields_to_sales_table.php` adds
  `client_reference` (nullable, 191), `client_created_at`, `synced_at`, and a
  unique index `sales_tenant_store_client_reference_unique`. NULL references are
  distinct, so online sales are never blocked.
- `Sale` model: new `SOURCE_ANDROID_OFFLINE` constant, new fillable/casts,
  transient `idempotentReplay` flag (non-persisted).
- `StoreSaleRequest`: accepts optional `source`, `client_reference`,
  `client_created_at`; still prohibits tenant/cashier/invoice/totals; a
  `withValidator` rule rejects `ANDROID_OFFLINE` + non-CASH.
- `SaleService::createCashSale`: looks up an existing sale by
  `(tenant, store, client_reference)` and returns it as an idempotent replay;
  otherwise creates a new sale stamped `ANDROID_OFFLINE`/`SYNCED`/`synced_at`.
  A unique-violation race falls back to the winning sale.
- `SaleResource`: single-sale responses expose `meta.idempotent_replay`.
- `SaleController::store`: 201 for a new sale, 200 for an idempotent replay.

## Backend Idempotency

- Same `(tenant, store, client_reference)` → existing sale, `idempotent_replay=true`, no duplicate.
- Same reference across **different tenants** → no collision (each owns its sale).
- Same reference with a **different payload** → original sale returned unchanged (safe).
- Online sales (no reference) are never deduplicated.

## Offline Sales API Rules

- `source = ANDROID_OFFLINE` is CASH-only; QRIS is rejected (422).
- Backend recalculates all totals and ignores any client totals (422 on forged totals).
- Backend snapshots product name + unit price into `sale_items`.
- `sync_status = SYNCED` and `synced_at = now()` on acceptance.

## Tenant Isolation Rules

- Tenant A cannot offline-sync using tenant B's `store_id` (422).
- Tenant A cannot offline-sync tenant B's product (422).
- Tenant A cannot see tenant B's offline sale (404 + excluded from list).
- Tenant A cannot exploit a shared `client_reference` to reach tenant B's sale
  (A gets its own new sale; no replay of B's).

## Android Implementation

- Entities: `LocalOfflineSaleEntity` (`offline_sales`), `LocalOfflineSaleItemEntity`
  (`offline_sale_items`); `OfflineSyncStatus` = PENDING/SYNCING/SYNCED/FAILED/CONFLICT.
- DAOs: `OfflineSaleDao` (abstract; atomic `insertOfflineSaleWithItems`, status
  transitions, counts) + `OfflineSaleItemDao`.
- `OfflineSaleRepository`: creates offline sale with a generated UUID reference,
  snapshots items, and replays pending/failed sales to the backend.
- `PosDatabase` v2 registers the new entities/DAOs; `ServiceLocator` wires the
  repository + `NetworkMonitor`.
- Cashier UI: "Simpan Cash Offline" action, "Sync Sekarang" action, Pending/Failed
  summary, and an offline **draft** receipt label.

## Local Offline Sale Storage

- `createOfflineCashSale` stores sale + items in one transaction and returns
  `Saved(localId, clientReference)`; the cart is cleared by the ViewModel **only**
  on `Saved`, kept on `Error`.

## WorkManager Sync

- `OfflineSalesSyncWorker` (CoroutineWorker) replays up to 10 pending/failed sales.
- `OfflineSalesSyncScheduler` enqueues unique work with `NetworkType.CONNECTED`
  and `BackoffPolicy.EXPONENTIAL`.

## Retry / Backoff Rules

- Success / idempotent replay → `SYNCED`.
- Transient (network/5xx) → `FAILED` (retryable; kept in queue); worker returns `retry()`.
- Validation/conflict (422/409) → `CONFLICT` (kept for resolution).
- Any exception → `retry()`; the worker never crashes the app.

## Sync Status UI

- `SyncCounts(pending, failed)` surfaced as "Menunggu sync: X, Gagal: Y".
- Manual "Sync Sekarang" triggers repository sync + schedules the worker.

## QRIS Online-Only Guard

- `NetworkMonitor` (lightweight ConnectivityManager check) + `QrisOnlineOnlyGuard`.
- `QrisPaymentViewModel.start` blocks QRIS creation when offline and shows
  "QRIS membutuhkan koneksi internet"; CASH offline remains allowed.

## Offline Receipt Draft

- After an offline save the cashier sees "STRUK OFFLINE / BELUM SYNC" with the
  client reference and local totals — clearly not a final server receipt. The
  Sprint 6 final-receipt rules are untouched; the server receipt is fetched via
  `serverSaleId` once synced.

## Android Build CI Evidence

- `.github/workflows/sprint7-ci.yml` job `android-build-test` runs JDK 21 +
  Android SDK, `:app:assembleDebug`, and `:app:testDebugUnitTest` (no
  `continue-on-error`). CI is the authoritative build gate.

## Application Rules Update

- `docs/PROJECT_RULES.md`: Foundation Lock Index updated to 9 entries; Sprint 7
  runtime rule appended.
- `backend/config/pos_foundation.php`: `offline_qris_forbidden`,
  `sales_idempotency_required`, `workmanager_sync_required`, `sprint_7` added.

## Testing Evidence

**Backend (`php artisan test`): 133 passed / 466 assertions.** New suites:
`OfflineSalesSyncApiTest`, `SalesIdempotencyTest`, `OfflineSalesTenantIsolationTest`.

**Android unit tests (run in CI):** `OfflineSaleRepositoryTest`,
`OfflineSalesSyncLogicTest`, `OfflineSaleMappingTest`, `QrisOnlineOnlyGuardTest`.

## Backend Compatibility Evidence

`composer validate --strict` passes; all Sprint 0–6 routes remain
(`/api/v1/sales`, `.../payments/cash`, `.../payments/qris`, `.../receipt`,
`/api/v1/payments/{payment}/status`, `/api/v1/webhooks/payments/{provider}`,
auth + sync). No prior test regressed.

## Validation Commands

```bash
bash scripts/sprint7_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- Sprint 7 smoke: pass (78/78 after CI + evidence created).
- Backend tests: pass (133/133).
- Composer validate: pass.
- Android build/tests: gated on CI (local JDK is 25; Gradle 8.11 requires ≤ 23).

## GO Criteria

See the 28-point GO list in the sprint brief. Key gates: rules locked, backend
idempotency + tenant isolation proven, Android offline storage + WorkManager +
retry/backoff + manual sync + QRIS offline guard + offline draft present, smoke +
backend tests green, and Android `assembleDebug` + `testDebugUnitTest` green in CI.

## No-Go Checks

Duplicate offline submit creating a duplicate sale; backend trusting Android
totals; tenant bleed via `client_reference`; missing offline storage/sync;
QRIS created offline; cart cleared before local save; failed sync deleting a
sale; unlabeled offline receipt; broken cash/QRIS/receipt/printer behavior; CI
not running or not green. None present.

## Follow-up for Sprint 8

Sprint 8 — Inventory Simple Foundation.
