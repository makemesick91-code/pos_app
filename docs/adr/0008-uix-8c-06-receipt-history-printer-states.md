# ADR 0008 — UIX-8C-06 Receipt binding, history reconciliation & printer failure-state architecture

- Status: Accepted
- Date: 2026-07-15
- Sprint: UIX-8C-06 (Android cashier full premium delivery & closure train)
- Supersedes/extends: ADR 0004/0005/0006/0007; rules 55/56/57/58/59; rule 61
  (UIX8C-R171..R210)

## Context

The receipt surface was a Sprint-6 foundation screen: a backend-only text
preview keyed by the **server** sale id, echoing raw decimal money strings, with
only Loading/Ready/Error states. Offline sales never reached it, there was no
history→receipt reprint path, and process restoration re-fetched from the network.
The transaction-history screen was a thin local-Room list with no merge/dedup
layer and no detail screen. The printer transport returned an untyped
`Success`/`Failure(String)` with connect-vs-write collapsed, no timeout, and no
catch-all. None of these had financial authority — but that was enforced only by
convention.

UIX-8C-06 rebuilds these three surfaces to a premium, truthful, accessible
standard **on top of** the UIX-8C-04/05 transaction foundation, without becoming a
second pricing/payment/QRIS/settlement/sync engine.

## Decision

1. **Receipt is a projection bound to one logical transaction.** A new immutable
   `ReceiptProjection` carries a `ReceiptIdentity` (stable `clientReference` +
   optional `serverSaleId` + optional local `localId`) and a truthful
   `ReceiptTransactionState` (ONLINE_SUCCESS / OFFLINE_PENDING / SYNCING / SYNCED /
   FAILED / CONFLICT). A pure `ReceiptProjector` builds it from **either** a local
   `LocalOfflineSaleEntity` + items (offline/pending path) **or** a backend
   `ReceiptDto` (online/synced path). The shared type is the parity guarantee.
   Money is whole-rupiah `Long`: local `Double` columns cross the single sanctioned
   `RupiahMoney.fromDouble` bridge; server decimal strings ("20000.00") are read to
   exact `Long` **without** `RupiahMoney.parse` (whose `.`-is-grouping semantics are
   for cashier input, not server decimals).

2. **The receipt screen is also the transaction detail / reopen-reprint screen.**
   One premium `ReceiptActivity` handles three governed launches — server sale id,
   offline `clientReference`, and local id (from history). This collapses "detail"
   and "receipt" into one surface bound to durable data.

3. **Stale-result prevention by identity.** The ViewModel binds to a requested
   `ReceiptIdentity` and only publishes a projection whose identity matches; print
   feedback is a one-shot `Event`, so rotation/process recreation never replays a
   previous transaction's result. Restoration derives truth from Room, not a stale
   in-memory event.

4. **History is reconciled, not raw.** A pure `TransactionHistoryReconciler` merges
   local and (future) server records into exactly one row per logical transaction,
   keyed on the stable `clientReference` (then `serverSaleId`). A local+server match
   collapses to one SYNCED row; a payload mismatch surfaces CONFLICT rather than a
   silent merge; a FAILED row under the bounded cap shows RETRY_SCHEDULED. There is
   no server history-list endpoint today, so the server list is empty — but the
   reconciler is the enforced guard the moment one is added, and it is exercised
   directly with synthetic local+server inputs.

5. **Printer is non-financial by construction.** A single `PrinterCoordinator`
   wraps the print seam behind an `AtomicBoolean` (at most one active job), exposes
   typed `PrintOutcome`, and has **no** reference to any sale/payment/sync/inventory
   type. Reprint reuses the same immutable receipt and creates no new transaction.
   The transport returns typed `PrinterFailure` reasons (permission required/denied,
   unsupported, adapter disabled, not configured, unavailable, connection failed,
   timeout, write failed, interrupted, unknown-safe), distinguishes connect vs.
   write, runs under a bounded timeout, and has a catch-all so it never crashes.
   Permissions stay least-privilege — `BLUETOOTH_CONNECT` only, no `BLUETOOTH_SCAN`.

## Alternatives considered

- **Add a server history-list endpoint + full merge now.** Rejected for this
  sprint: out of scope (no new backend source), and the reconciler already prevents
  duplicates the moment such a feed lands. A backend gap would be fixed in the
  canonical service, not hidden in the Android projection.
- **Keep printing enabled for offline drafts.** Rejected: the ESC/POS formatter
  consumes a backend-approved `ReceiptDto`; a pending offline draft has none.
  Printing stays disabled until sync, with truthful messaging, honoring
  UIX8C-R191.
- **Overload `RupiahMoney.parse` for server strings.** Rejected: it treats `.` as a
  thousands separator (correct for Indonesian cashier input, wrong for server
  decimals). A dedicated integer read avoids a 100× money bug and any float.

## Consequences

- No second transaction path: the receipt/history/printer code plugs into
  `OfflineSaleRepository`, `OfflineSaleDao`, `ReceiptRepository`, `RupiahMoney`,
  `PaymentUiState`, and the stable `clientReference`.
- Financial isolation of the printer is now type-level and gate-enforced.
- Physical receipt/history/printer/large-font/TalkBack validation remains
  operator-performed and deferred until final code freeze; the immutable failed
  physical run stays verbatim. UIX-7 stays `NO-GO — GO DEFERRED`; UIX-8 stays
  `IMPLEMENTATION COMPLETE — GO DEFERRED`.
