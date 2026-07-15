# UIX-8C-06 — Receipt Binding & History Reconciliation Architecture

## Layering

```
ReceiptActivity ──► ReceiptViewModel ──► ServerReceiptSource (ReceiptRepository)
                                    └──► LocalReceiptSource  (OfflineSaleRepository)
                                    └──► PrinterCoordinator   (non-financial)
                         │
                         ▼
                 ReceiptProjector ──► ReceiptProjection (immutable, whole-rupiah)

TransactionHistoryActivity ──► TransactionHistoryViewModel ──► OfflineSaleRepository.recentSales()
                                                          └──► TransactionHistoryReconciler ──► List<HistoryRow>
```

Narrow interface seams (`ServerReceiptSource`, `LocalReceiptSource`,
`ReceiptPrinter`) let the ViewModel and coordinator be unit-tested on the JVM with
hand fakes — no Retrofit, no Room, no Context.

## Receipt binding chain

```
current checkout attempt
  → stable clientReference (minted once in CashierViewModel, reused across
    online attempt / offline fallback / retry / restart / reconnect)
  → local offline transaction (LocalOfflineSaleEntity.localId) when offline
  → server sale id (LocalOfflineSaleEntity.serverSaleId) once acknowledged
  → ReceiptIdentity(clientReference, serverSaleId, localId)
  → ReceiptProjection (state derived from OfflineSyncStatus / server ack)
  → ReceiptViewModel publishes ONLY when identity matches the request
```

Money projection:
- local `Double` columns → `RupiahMoney.fromDouble` (single sanctioned bridge);
- server decimal strings ("20000.00") → integer part before '.' → `Long`
  (NOT `RupiahMoney.parse`, whose '.'-is-grouping rule is for cashier input).

## History reconciliation

```
local PENDING/SYNCING/FAILED/CONFLICT rows  +  server-confirmed rows (future)
  → group by mergeKey (clientReference → serverSaleId → localId)
  → per group:
      local+server total mismatch OR local CONFLICT  → CONFLICT row
      server-confirmed OR local SYNCED               → SYNCED row
      else local status → PENDING / SYNCING /
           (FAILED under cap → RETRY_SCHEDULED, else FAILED)
  → one HistoryRow per logical transaction
  → sort createdAt desc, tiebreak by key (stable)
```

Merge key is transaction identity, never amount/timestamp (R182). Refresh,
reconnect, worker ack, and process restart are idempotent — one row throughout
(R186).

## Reuse (no second path)

`clientReference` lifecycle, `OfflineSaleRepository` (durable persistence + new
read-only `findSaleWithItems*`), `OfflineSaleDao`/`OfflineSaleItemDao`,
`OfflineSyncStatus`, `RupiahMoney`, `PaymentUiState`, `ReceiptRepository`, backend
idempotency. No new checkout/offline/sync/backend-sale pipeline is introduced.
