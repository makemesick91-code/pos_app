# UIX-8C-05 — Premium Cash Payment, Offline Queue & Sync Recovery UX: Initial Audit

- Sprint: UIX-8C-05
- Package: `com.aishtech.poslite` (native Android: Views/XML + Retrofit/OkHttp + Room + WorkManager + ViewModel/LiveData)
- Toolchain: JDK 21, minSdk 26, targetSdk 35, pilot API `https://aishpos.online/`
- Baseline: `origin/main` = `f6045b4` (UIX-8C-04 final); UIX-8C-04 anchor `5063eb4`, tag `uix-8c-04-offline-cash-durability-idempotent-recovery-go`

## Purpose

Audit the current cash payment / offline-queue / sync-recovery surface before
building the UIX-8C-05 premium presentation layer, so the new work **reuses** the
UIX-8C-04 transaction foundation rather than forking a second engine.

## Current payment architecture

The cash checkout path is already governed and durable after UIX-8C-04:

- `PaymentSheetFragment` collects tender and hands off to the host, which calls
  `CashierViewModel.checkoutCash(...)`. This is the single guarded entry point
  (ViewModel-level double-submit guard + stable `clientReference`).
- `SalesRepository.submitCash` returns a typed `CheckoutOutcome`
  (Success / Rejected / TransportUnavailable / Failed).
- On an Eligible transport failure the ViewModel performs a durable offline CASH
  save via `OfflineSaleRepository.createOfflineCashSale` and keeps the cart on
  any rejection/failure.

## UI / domain boundary

The presentation layer must present and orchestrate only. Pricing, tax, payment,
QRIS, settlement, and sync truth stay in the backend `App\Services\*` domains and
the app's canonical repositories. UIX-8C-05 adds **pure, JVM-testable**
presentation components and a projection mapper — never a second state machine.

## Reusable UIX-8C-04 components (reuse, never duplicate)

- `core/network/TransportFailureClassifier.kt` — fail-closed `classify(throwable)`;
  TLS/HTTP are never offline-eligible.
- `data/repository/SalesRepository.kt` — `CheckoutOutcome`.
- `data/repository/OfflineSaleRepository.kt` — `createOfflineCashSale`,
  `findByClientReference`, `MAX_SYNC_ATTEMPTS = 5`, `syncPending`, orphan-SYNCING
  recovery, `pendingCount`/`failedCount`.
- Stable `clientReference` lifecycle in `CashierViewModel.kt`
  (`pendingCheckoutReference` + `checkoutReference()`, minted once, reused across
  online attempt / fallback / restart / reconnect / worker replay).
- `feature/sync/OfflineSalesSyncScheduler.kt`
  (`UNIQUE_WORK_NAME = "offline-sales-sync"`, `NetworkType.CONNECTED`,
  `BackoffPolicy.EXPONENTIAL`, `ExistingWorkPolicy.KEEP`) +
  `OfflineSalesSyncWorker.kt` (`CoroutineWorker`).
- Room: `OfflineSyncStatus.kt` (PENDING/SYNCING/SYNCED/FAILED/CONFLICT),
  `LocalOfflineSaleEntity.kt`, `OfflineSaleDao.kt`
  (`@Transaction insertOfflineSaleWithItems`), whole-rupiah `Long` money via
  `core/money/RupiahMoney.kt`.
- Backend dedupes by `(tenant, store, client_reference)`; UIX-8C-05 adds **no**
  backend source — only a regression fence
  `backend/tests/Feature/PaymentSyncUxIdempotencyRegressionTest.php`.

## Gaps this sprint closes

- **Duplicated-logic risk avoided.** No new checkout, persistence, WorkManager, or
  money-math path; the new mapper is a projection over canonical state.
- **State-machine gaps.** Boolean-soup presentation replaced by a single sealed
  `PaymentUiState` (13 states) with fail-closed transition validation.
- **Accessibility gaps.** Missing live region on change/validation text, unlabeled
  icon controls, undefined focus order.
- **Large-font risk.** The confirm CTA could be pushed off-screen; fixed by a
  `NestedScrollView` root, verified at 100/115/130% font.
- **Reconnect / retry UX gaps.** No informative reconnect feedback and no governed
  manual retry; added via a least-privilege network callback + `SyncRecoveryPresenter`.

## Implementation plan

1. Pure components: `TenderValidator`, `QuickTenderCalculator`.
2. Presentation state: `PaymentUiState` + `PaymentUiStateMapper` (projection).
3. Recovery UX: `SyncRecoveryPresenter`, `CashierViewModel.paymentUiState`,
   `requestManualRetry()`, `onConnectivityRestored()`.
4. Wiring: `PaymentSheetFragment`, `CashierActivity` network callback,
   `res/layout/view_payment_sheet.xml`.
5. Tests + regression fence (see the test matrix doc).

## Scope exclusions

- No receipt or transaction-history rebuild.
- No QRIS offline (QRIS stays online-only).
- No physical campaign in this sprint.
- No backend source change (regression test only).
- No Room/schema, dependency, or WorkManager behaviour change.
