# UIX-8C-04 — Offline CASH Durability & Idempotent Recovery Architecture

Permanent architecture for the durable offline CASH path and its idempotent
recovery. Extends rules 55/56/57/58/59 and UIX8C-R001..R095; introduces
UIX8C-R096..R130.

## Canonical flow

```
operator confirms CASH tender
  → CashierViewModel.checkoutCash(paid)
    → re-entry guard (Submitting) · empty-cart guard · RupiahMoney.isSufficient
    → mint ONE stable clientReference (checkoutReference())            [R097]
    → SalesRepository.submitCash(items, paid, reference)
        → PosApiService.createSale(...)
        ├─ HTTP 2xx        → CheckoutOutcome.Success(sale)
        ├─ HTTP error      → CheckoutOutcome.Rejected(code)   (reachable)  [R099..R102]
        └─ thrown          → TransportFailureClassifier.classify(t)
                              ├─ Eligible   → CheckoutOutcome.TransportUnavailable [R098]
                              └─ Ineligible → CheckoutOutcome.Failed (TLS/unknown) [R103]
    → when Success            : cart.clear(); reference=null; Success           [R107]
    → when TransportUnavailable: saveOfflineFallback(...)                        [R098]
    → when Rejected / Failed  : keep cart; Error (NEVER offline)                 [R099..R103,R108]

saveOfflineFallback(items, paid, reference, total)
  → OfflineSaleRepository.createOfflineCashSale(items, paid, clientReference=reference)
      → findByClientReference(reference) → if present, reconcile (no dup)        [R109]
      → OfflineSaleDao.insertOfflineSaleWithItems(header+items)  [one @Transaction] [R106]
        └─ on unique-conflict race → findByClientReference → reconcile           [R109]
      ← SaveResult.Saved(localId, reference)
  → cart.clear(); OfflineSaved(reference,total,change); refreshSyncCounts()      [R105,R107]
  → (on SaveResult.Error) keep cart; Error                                       [R108]

CashierActivity renders OfflineSaved
  → truthful "tersimpan di perangkat ini dan menunggu sinkronisasi" text        [R124]
  → OfflineSalesSyncScheduler.enqueue(applicationContext)                        [R114,R115]
```

## Components

| Component | Role | Rules |
|-----------|------|-------|
| `core/network/TransportFailureClassifier` | Deterministic, allow-listed, fail-closed classification of a thrown failure into Eligible / Ineligible. Walks the cause chain; carries no payload. | R098/R103/R127 |
| `SalesRepository.submitCash` → `CheckoutOutcome` | Single online attempt → typed outcome. A received HTTP status ⇒ reachable ⇒ never offline. | R099..R103 |
| `CashierViewModel` (`pendingCheckoutReference`, `checkoutReference`, `saveOfflineFallback`) | Owns the stable key + the clear-after-durable-outcome decision. | R097/R105/R107/R108 |
| `OfflineSaleRepository.createOfflineCashSale(clientReference)` | Idempotent durable save reusing the stable key. | R106/R109/R121 |
| `OfflineSaleDao.findByClientReference` + unique index | Local dedupe / reconciliation. | R109 |
| `OfflineSalesSyncScheduler` / `OfflineSalesSyncWorker` | Bounded, network-constrained, unique-work sync; orphan-SYNCING recovery via `getPendingOrFailed`. | R110/R111/R115/R117 |
| `SaleService::createCashSale` (backend, unchanged) | Authoritative dedupe by `(tenant, store, client_reference)`. | R118..R122 |

## State machine (local row)

`PENDING → SYNCING → SYNCED` (only on server ACK durably recorded); `→ FAILED`
(retryable while `syncAttemptCount < MAX_SYNC_ATTEMPTS`); `→ CONFLICT` (422/409).
Orphan `SYNCING` rows (process death mid-attempt) are re-selected for retry and
are never capped. A row is **never** SYNCED before a canonical ACK (R111).

## Money

Whole-Rupiah `Long` on the authoritative path (`RupiahMoney`). The offline row's
`Double` columns are a single projection boundary of the integer value
(consistent with UIX8-R016); no new float arithmetic is introduced (R121).

## clientReference lifecycle

Minted once per logical checkout (`checkoutReference()`), reused across: the
online submit, a retry after a lost response, the governed offline fallback, and
the manual offline path. Reset only on a durable **Success** or any cart mutation
(add/update/remove/clear) — a changed cart is a new logical transaction (R097).
It survives process restart because it is persisted on the durable row (R113).

## No schema change

The `offline_sales` / `offline_sale_items` entities and columns are unchanged;
the existing unique index on `clientReference` already backs the idempotency.
No Room migration is required or added.
