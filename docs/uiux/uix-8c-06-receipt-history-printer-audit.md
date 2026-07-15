# UIX-8C-06 — Receipt, Transaction-History & Printer Audit

Pre-implementation audit of the three surfaces UIX-8C-06 rebuilds, the duplication
and stale-result risks found, and the proposed remediation. Baseline: `origin/main`
at the UIX-8C-05 closure (`949f1a1`).

## Current architecture (before UIX-8C-06)

### Receipt (`feature/receipt`)
- `ReceiptActivity` + `ReceiptViewModel` only. Bound to the **server sale id**
  (`EXTRA_SALE_ID`), loaded from the backend via `ReceiptRepository.getReceipt`.
- Money echoed as raw server strings ("20000.00"); `RupiahMoney` unused here.
- States: Loading / Ready / Error only. **No** offline/PENDING/SYNCING/SYNCED/
  FAILED/CONFLICT.
- Offline sales never reach the receipt; no history→reprint path; restoration is a
  network re-fetch of the surviving Intent id, not Room.

### Transaction history (`feature/history`)
- `TransactionHistoryViewModel` → `OfflineSaleRepository.recentSales()` →
  `OfflineSaleDao.getRecent()`. **Local Room only**; no server history endpoint.
- States: Loading/Loaded/Empty/Error + per-row sync badge (`SyncStatusDisplay`, all
  five states present). No detail screen, no row click, no merge/dedup layer.
- Money bridged through `RupiahMoney.fromDouble` at the display boundary.

### Printer (`feature/printer`)
- `PrinterConnection`/`PrintResult` (Success / `Failure(String)`),
  `BluetoothPrinterConnection` (paired BT Classic SPP, **no discovery**, no
  `BLUETOOTH_SCAN`), `PrinterRepository` (backend-gated `printable`),
  `EscPosReceiptFormatter`.
- Financial isolation held **by convention** only. Connect vs. write collapsed into
  one IOException message; no timeout; no catch-all.

## Risks identified

| Risk | Rule |
|------|------|
| Receipt not bound to `clientReference` (only server id) — cannot bind offline sales | R172 |
| Offline sale has no receipt surface; no truthful PENDING receipt | R175 |
| Raw server decimal money echoed; `RupiahMoney.parse` would 100×-misread "20000.00" | R179 |
| No history merge/dedup layer → duplicate rows the moment a server feed lands | R181/R182 |
| No reopen/reprint; restoration is network, not Room | R187/R189 |
| Printer failure untyped; connect/write indistinguishable; no timeout/catch-all | R197/R200 |
| Printer financial isolation only by convention | R191/R192 |

## Proposed remediation (implemented)

- Immutable `ReceiptProjection` + `ReceiptIdentity` + pure `ReceiptProjector`
  (local + server → one parity type; whole-rupiah `Long`; server strings read to
  exact integer without `parse`).
- One premium `ReceiptActivity` = receipt + transaction detail + reopen/reprint,
  launched by server id, offline `clientReference`, or local id.
- Identity-guarded ViewModel + one-shot `Event` print feedback; restoration from
  Room.
- Pure `TransactionHistoryReconciler` (one row per logical transaction; merge/
  dedup/conflict) + `HistoryDisplayState` + row detail navigation.
- Single non-financial `PrinterCoordinator` (concurrency-guarded) + typed
  `PrinterFailure`/`PrintOutcome`; bounded timeout, connect/write split, catch-all;
  least-privilege permissions preserved.

## Out of scope

- A server history-list endpoint / full server-history merge (reconciler is
  future-proofed; no new backend source).
- Enabling QRIS offline; any change to `SaleService`/Room/financial behaviour
  (backend regression fence only).
- Physical-device campaign, GO tag beyond the sprint-scoped tag, UIX-7/UIX-8 GO.
