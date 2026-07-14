# ADR 0007 — UIX-8C-05 Payment / Sync Presentation State Machine

- Status: Accepted
- Date: 2026-07-15
- Sprint: UIX-8C-05
- Supersedes: none
- Related: rules 55/56/57/58/59/61; ADR 0004/0005/0006

## Context

UIX-8C-04 gave the cashier a durable offline CASH path (typed transport
classifier, governed online→offline fallback, atomic Room save, stable
`clientReference`, bounded WorkManager retry, orphan-SYNCING recovery) but did not
rebuild the premium payment surface — only the truthful offline-queued state. The
cash payment sheet, quick-tender, tender validation, and the sync-recovery UX
still needed a premium, accessible, truthful presentation.

The risk is that a premium payment UX quietly becomes a *second* engine: a UI that
recomputes money, owns its own sync truth, or optimistically claims `SYNCED`. The
presentation state must be truthful and **distinct** from canonical checkout/sync
truth without duplicating any transaction logic.

## Decision

1. **Introduce a presentation state, not an engine.** `PaymentUiState` (13 sealed
   states) + `PaymentUiStateMapper` (pure `object`) project canonical
   `CheckoutState` + `OfflineSyncStatus` into UI states. `OfflineSaved` maps to
   `OfflineQueued` (never `Synced`); only a recorded `SYNCED` ack maps to `Synced`.
   `isAllowedTransition` is class-matched and rejects invalid transitions fail-closed.

2. **Pure, JVM-testable input components.** `TenderValidator`
   (Empty/Invalid/Insufficient/Valid, change never negative, delegates to
   `RupiahMoney.parse`) and `QuickTenderCalculator` (≤3 strictly-above-due,
   overflow-safe) contain no side effects and no transaction logic.

3. **Governed manual retry via `SyncRecoveryPresenter`.** `canManualRetry = FAILED
   && attempts < cap`; never for CONFLICT, poison-at-cap, or PENDING/SYNCING/SYNCED.
   `CashierViewModel.requestManualRetry()` delegates to canonical `syncPending`;
   `onConnectivityRestored()` is a one-shot event that refreshes counts and creates
   **no** new work (unique work + `KEEP` owns scheduling).

4. **Reuse the entire UIX-8C-04 transaction foundation.** No new checkout, offline
   persistence, `clientReference`, WorkManager, money-math, or backend sale path.
   Backend gets a regression fence only
   (`PaymentSyncUxIdempotencyRegressionTest.php`).

## Alternatives rejected

- *A second sync/checkout engine in the UI.* Rejected: it would fork transaction
  authority and risk duplicate sales/payments — an automatic NO-GO.
- *A boolean-soup state model* (isLoading/isOffline/isSynced flags). Rejected:
  ambiguous, untestable, and prone to claiming states the domain never recorded.
- *Optimistic `SYNCED`* (show synced before server ack). Rejected: violates the
  truthful-state invariant (SYNCED only on recorded canonical acknowledgement).

## Consequences

- Truthful, distinct payment/sync states; fail-closed invalid transitions; no
  duplicated transaction logic (the mapper is a projection).
- Manual retry is bounded and idempotent; conflicts are never silently retried.
- Physical closure is still deferred: a fresh physical R11 + payment/sync UX
  revalidation on the frozen final APK remains mandatory. UIX-7 stays
  `NO-GO — GO DEFERRED`; UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`. The
  historical R11 evidence stays immutable.
