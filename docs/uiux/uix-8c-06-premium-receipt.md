# UIX-8C-06 — Premium Receipt / Transaction Detail

The receipt is a **projection of one canonical transaction** (`ReceiptProjection`).
It is never a second source of financial truth and never recomputes money.

## Binding

A receipt carries a `ReceiptIdentity` = stable `clientReference` + optional
`serverSaleId` + optional local `localId`. Three governed launches:

- `ReceiptActivity.forServerSale(saleId, clientReference?)` — online-acknowledged
  sale; backend receipt is authoritative and printable when the backend approves.
- `ReceiptActivity.forOfflineReference(clientReference)` — a just-saved durable
  offline transaction (truthful PENDING draft).
- `ReceiptActivity.forLocalTransaction(localId)` — reopen a durable local
  transaction from history (detail + reprint).

The ViewModel publishes a projection **only** when its identity matches the
request, so a previous transaction's receipt can never surface for the current
checkout (R173/R190).

## States (text + colour, never colour alone)

| State | Meaning | Printable |
|-------|---------|-----------|
| ONLINE_SUCCESS | server acknowledged this checkout | yes (if backend `printable`) |
| OFFLINE_PENDING | durably saved locally, awaiting sync | no (until synced) |
| SYNCING | sync in flight | no |
| SYNCED | server ack recorded locally | yes (reprint via backend receipt) |
| FAILED | transient sync failure; retry scheduled | no |
| CONFLICT | server rejected; needs review | no |

A durable save projects to **OFFLINE_PENDING**, never SYNCED (R175). SYNCED shows
only when the canonical `SYNCED` status is recorded (R176).

## Fields & parity

Business, outlet, cashier, reference, date, per-line (name, qty × unit price, line
total), subtotal, discount, tax, grand total, tender, change, payment method. All
money is whole-rupiah `Long` and matches the canonical transaction exactly (R177).
Tender/change render `formatOrUnavailable` — a genuinely absent value shows
"Tidak tersedia", never a fabricated 0 (R013).

## Layout & accessibility

Content lives in a `NestedScrollView`; the print + new-transaction actions are
pinned below it and stay reachable at 100/115/130% font (R206). Touch targets are
≥48dp (R202). Print feedback is announced for TalkBack (R203). No hardcoded hex or
dp type sizes.
