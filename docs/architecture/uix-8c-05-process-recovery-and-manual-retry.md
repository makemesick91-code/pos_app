# UIX-8C-05 — Process Recovery, Reconnect Feedback & Safe Manual Retry

- Sprint: UIX-8C-05
- Components: `feature/cashier/CashierViewModel.kt`,
  `feature/sync/SyncRecoveryPresenter.kt`, `feature/cashier/CashierActivity.kt`
- Package: `com.aishtech.poslite`

## Purpose

Describe how the payment/sync UX survives process death, gives honest reconnect
feedback, and offers a **safe** manual retry — all by reusing the UIX-8C-04
canonical transaction foundation, never by inventing a second sync path.

## Durable truth vs ephemeral tender

- **Durable truth** lives in Room (`LocalOfflineSaleEntity`, unique
  `clientReference`). A queued transaction is a durable row with a canonical status.
- **Ephemeral tender** (the in-progress amount typed into the sheet) is *not*
  durable and is not expected to survive process death — only the committed
  transaction is. On restore, the app reads canonical rows, never a reconstructed
  guess.

## Five process-death scenarios

| # | Death point | Restored behaviour |
|---|-------------|--------------------|
| 1 | Pre-submit (tender only) | No transaction exists; cashier re-enters tender. Cart preserved. |
| 2 | Submitting, before durable commit | No durable row; same `clientReference` reused on retry — backend dedupes. |
| 3 | After local commit | Durable `PENDING` row restored; projects to `OfflineQueued`/`Pending`. Never lost. |
| 4 | After server commit, before response | Same `clientReference` replays; backend returns the existing sale — exactly one. |
| 5 | After `SYNCED` | Durable `SYNCED` row restored; shown as `Synced`, never re-submitted. |

The stable `clientReference` (`pendingCheckoutReference` + `checkoutReference()`)
is minted once and reused across online attempt, offline fallback, process
restart, reconnect, and worker replay — the idempotency anchor for scenarios 2–4.

## Reconnect feedback

- `CashierActivity` registers a **least-privilege** default-network callback
  (`ACCESS_NETWORK_STATE` only) that fires `onConnectivityRestored()` **only** on a
  genuine unavailable → available edge.
- `CashierViewModel.onConnectivityRestored()` emits a **one-shot** `Event` and
  refreshes `pendingCount`/`failedCount`. It **creates no new work** — the
  canonical `OfflineSalesSyncScheduler` (unique work, `KEEP`) already owns sync
  scheduling, so reconnect never spawns a duplicate worker.

## Safe manual retry

- `CashierViewModel.requestManualRetry()` delegates to the canonical
  `syncNow` → `OfflineSaleRepository.syncPending`; it is guarded and reuses the
  existing transaction + `clientReference`. It never re-rings a sale.
- Bounded retry is respected: `MAX_SYNC_ATTEMPTS = 5`. Worker coordination is via
  the unique work name with `ExistingWorkPolicy.KEEP`, so a manual retry cannot
  race a scheduled run into a duplicate.
- `SyncRecoveryPresenter.present(status, attempts, cap)` → `SyncRecoveryUi(status,
  label, isRetryable, showManualRetry, isTerminal)`:
  - `canManualRetry = FAILED && attempts < cap`.
  - **Never** for `CONFLICT`, never for a poison row at cap, never for
    `PENDING`/`SYNCING`/`SYNCED`.
- A `CONFLICT` is never silently retried; resolution is explicit and governed
  (transition `Conflict → Idle`). TLS/security failures are classified by
  `TransportFailureClassifier` as never-offline and are not retryable here.

## Rules

UIX8C-R157–R159 (manual retry reuses transaction/`clientReference`, bounded retry
+ worker coordination), R160 (conflict never silently retried), plus R145/R147/R148
truthful-state invariants and R149 (canonical/TLS rejection never becomes offline
success).
