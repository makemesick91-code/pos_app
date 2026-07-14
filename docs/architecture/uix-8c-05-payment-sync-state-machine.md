# UIX-8C-05 — Payment / Sync Presentation State Machine

- Sprint: UIX-8C-05
- Components: `feature/cashier/PaymentUiState.kt`,
  `feature/cashier/PaymentUiStateMapper.kt`
- Package: `com.aishtech.poslite`

## Purpose

Define the **presentation** state machine for the cash payment + offline-sync UX.
It is a truthful *projection* of canonical checkout and sync truth — not a second
checkout, persistence, or sync engine. Canonical authority stays in
`SalesRepository` / `OfflineSaleRepository` / the WorkManager path and the backend.

## The 13 states (`PaymentUiState`, sealed)

1. `Idle`
2. `EditingTender`
3. `Ready`
4. `SubmittingOnline`
5. `PersistingOffline`
6. `OnlineSuccess(sale)`
7. `OfflineQueued(clientReference, grandTotal, change)`
8. `Pending`
9. `Syncing`
10. `RetryScheduled`
11. `Failed(message, retryable)`
12. `Conflict(clientReference)`
13. `Synced`

## Allowed transitions

| From | Allowed to |
|------|------------|
| `Ready` | `SubmittingOnline` |
| `SubmittingOnline` | `OnlineSuccess`, `PersistingOffline`, `Failed`, `Conflict` |
| `PersistingOffline` | `OfflineQueued`, `Failed` |
| `OfflineQueued` | `Pending` |
| `Pending` | `Syncing`, `RetryScheduled`, `Failed` |
| `Syncing` | `Synced`, `RetryScheduled`, `Failed`, `Conflict` |
| `RetryScheduled` | `Syncing` |
| `Failed` | `Syncing`, `RetryScheduled`, `Idle` |
| `Conflict` | `Idle` (explicit governed resolution only) |

`Idle` / `EditingTender` / `Ready` form the tender-entry lead-in.
`PaymentUiStateMapper.isAllowedTransition(from, to)` matches **by class**; any
transition not in this table is **rejected fail-closed**.

## Two critical invariants

1. **Durable save → `OfflineQueued`, never `Synced`.** A successful local commit
   projects to `OfflineQueued` (truthful "waiting to sync"). It never claims
   server synchronization (UIX8C-R145, R147).
2. **`Synced` only from a canonical SYNCED ack.** `fromSyncStatus` maps only a
   recorded `OfflineSyncStatus.SYNCED` to `Synced`; nothing else fabricates it
   (UIX8C-R148).

## How the mapper projects canonical state

`PaymentUiStateMapper` is a pure `object` and a **projection**, not a state owner:

- `fromCheckout(CheckoutState)`:
  - `OfflineSaved` → `OfflineQueued` (**never** `Synced`).
  - `Error` → `Failed(retryable = false)`.
- `fromSyncStatus(status, attempts, cap)`:
  - `SYNCED` → `Synced` (only).
  - `FAILED` under cap → `RetryScheduled`; at cap → `Failed(retryable = true)`.
  - `CONFLICT` → `Conflict`.
  - unknown → `Failed` (fail-closed).
- `isAllowedTransition(from, to)` — class-matched; invalid transitions rejected.

Because the mapper derives every state from canonical `CheckoutState` +
`OfflineSyncStatus` + attempt count, the UI can never assert a truth the domain
has not recorded. Optimistic `SYNCED` and boolean-soup are structurally impossible.

## Rules

UIX8C-R144 (online success only on server ack), R145 (offline-queued only after
durable commit), R147 (queued/pending never claims sync), R148 (SYNCED only on
recorded ack), R149 (canonical/TLS rejection never becomes offline success),
R160 (conflict never silently retried).
