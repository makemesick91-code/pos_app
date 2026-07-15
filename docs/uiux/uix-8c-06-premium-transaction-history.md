# UIX-8C-06 — Premium Transaction History

A read-only, **reconciled** list: exactly one row per logical transaction, with
explicit, accessible sync-state badges. Tapping a row opens the durable
transaction detail / receipt.

## Reconciliation

`TransactionHistoryViewModel` reads local records (`OfflineSaleRepository
.recentSales()`), normalizes them to `HistoryRecord`, and runs
`TransactionHistoryReconciler.reconcile(local, server = [])`:

- group by `mergeKey` (stable `clientReference`, then `serverSaleId`, then localId);
- a local+server match collapses to **one** SYNCED row (R181/R182);
- a local/server total mismatch → **CONFLICT** (never a silent merge, R160);
- order newest-first with a stable tiebreak (R186); idempotent across refreshes.

There is no server history-list endpoint today, so the server list is empty and
each local transaction is one row. The reconciler is the enforced dedup guard the
moment a server feed is introduced.

## States (distinct, textual)

Screen: Loading / Loaded / Empty / Error (empty ≠ error). Per row:
PENDING / SYNCING / RETRY_SCHEDULED / SYNCED / FAILED / CONFLICT / UNKNOWN via
`HistoryStateDisplay` — each a distinct label + colour (never colour alone, R205).
RETRY_SCHEDULED (a FAILED row under the bounded cap) and CONFLICT are textually
distinct from a terminal FAILED.

## Rows & navigation

Each row shows total (whole-rupiah), reference, date/time, and state. Rows are a
`ListAdapter` with `DiffUtil` and stable ids. A row is a ≥48dp focusable/clickable
target with a TalkBack content description; tapping opens
`ReceiptActivity.forLocalTransaction(localId)`. History reloads on `onResume` so a
sale that synced while away is reflected without replaying a stale event (R186/R187).

## Scoping

History is tenant/device scoped by the per-tenant Room database (UIX-7). The
reconciler preserves that scope — cross-tenant records never merge (R183).
