# UIX-8C-04 — Offline CASH Durability: Root-Cause Analysis

Status: **Root cause fixed** (source remediation + automated regression).
Scope: the P1 financial-integrity defect observed as physical run
`run-97fbb64-2af94aa` finding **R11 (FAIL)** — "Offline CASH sale not persisted
durably".

This document traces the *actual code paths* on the baseline commit before the
fix. It does not restate a plan; it records what the code did and why the
transaction was lost.

## 1. Observed physical failure (immutable, unchanged)

From `docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json` (R11, FAIL):

```
Physical device offline CASH checkout attempted the backend.
DNS/transport failure was surfaced as a hard failure.
No durable offline_sales row was created.
on-device offline_sales count remained 0.
No locally durable PENDING transaction existed.
The transaction therefore could not survive process death or reconnect.
```

This record is **not** modified by this sprint (UIX8C-R129).

## 2. Pre-fix call graph (baseline)

```
PaymentSheetFragment ("Bayar Online" button)
  → CashierActivity.onCashTender(paidAmount, offline=false)
    → CashierViewModel.checkoutCash(paidAmount)
      → SalesRepository.checkoutCash(items, paidAmount, reference)
        → PosApiService.createSale(request)         [Retrofit suspend]
          → OkHttp transport → UnknownHostException  (backend unreachable)
        ← catch (e: Exception) → ResultState.Error(e.message)
      ← ResultState.Error
    → CheckoutState.Error(message)      ← ✗ transaction lost here
```

The offline path was a **separate, manually-selected** button:

```
PaymentSheetFragment ("Simpan Cash Offline" button)
  → CashierActivity.onCashTender(paidAmount, offline=true)
    → CashierViewModel.checkoutCashOffline(paidAmount)
      → OfflineSaleRepository.createOfflineCashSale(...)  → Room insert (PENDING)
```

## 3. Exact root cause

Two independent defects, both on the authoritative money path:

1. **No governed transport fallback (primary R11 cause).** The online CASH path
   (`CashierViewModel.checkoutCash`) mapped *every* `ResultState.Error` — including
   a genuine DNS/transport failure — to `CheckoutState.Error` and stopped. It
   never called the offline persistence path. Durable offline save was reachable
   only if the operator *manually* pressed a different "Simpan Cash Offline"
   button *before* attempting. On a real device with no backend reachability, the
   operator pressed "Bayar Online", the request threw `UnknownHostException`, and
   the transaction was surfaced as a hard error with **zero** `offline_sales`
   rows written. Nothing durable existed to survive process death or reconnect.

2. **No stable `clientReference` bridge online→offline.**
   `OfflineSaleRepository.createOfflineCashSale` minted its *own* reference
   (`referenceProvider()`), ignoring any reference the online attempt had used.
   Even if a fallback had existed, the offline row and the earlier online attempt
   would have carried *different* idempotency keys — so a timeout-after-commit
   online attempt followed by an offline replay could have created two backend
   sales.

### Why local persistence was bypassed

`SalesRepository.checkoutCash` used an untyped `catch (e: Exception)` that
collapsed *all* failure classes into one opaque `ResultState.Error`. The
ViewModel could not tell a governed transport failure (safe to queue offline)
apart from a canonical rejection (must NOT queue) — so the only safe design left
was "never queue automatically", which is precisely the durability gap.

## 4. Transaction boundary (pre-fix, unchanged by the fix)

Local persistence itself was already atomic: `OfflineSaleDao
.insertOfflineSaleWithItems` wraps the header + items insert in a single
`@Transaction`, and `LocalOfflineSaleEntity` already had a `unique` index on
`clientReference`. The gap was never the *atomicity* of the save — it was that
the save was **never invoked** on a transport failure.

## 5. Backend idempotency (pre-fix, verified sufficient)

`SaleService::createCashSale` already pre-checks `findByClientReference(tenant,
store, client_reference)` and is backed by the unique index
`sales_tenant_store_client_reference_unique`, with a `QueryException`
catch-and-refetch for the concurrency race; payment + `sale_items` + inventory
`SALE_OUT` are all created inside the same `DB::transaction` and are skipped on a
replay. **No backend source gap was found** — see the audit summarised in the
test matrix. UIX-8C-04 adds regression tests only (UIX8C-R118..R122).

## 6. Remediation (implemented)

| # | Change | Rule |
|---|--------|------|
| 1 | Typed `core/network/TransportFailureClassifier` (allow-listed transport failures eligible; TLS/unknown ineligible; fail-closed) | R098/R103 |
| 2 | `SalesRepository.submitCash` returns typed `CheckoutOutcome` (Success / Rejected / TransportUnavailable / Failed) | R099..R103 |
| 3 | `CashierViewModel.checkoutCash` degrades to a durable offline save on an eligible transport failure only; keeps the cart on a rejection/TLS error | R098/R107/R108 |
| 4 | One stable `pendingCheckoutReference` reused across online attempt, fallback, and the manual offline path | R097 |
| 5 | `OfflineSaleRepository.createOfflineCashSale(clientReference=…)` reuses the key and is idempotent via `findByClientReference` (+ unique-conflict reconcile) | R109 |
| 6 | Cart clears only after a durable save; truthful `cashier_offline_waiting_sync` state; sync enqueued on `OfflineSaved` | R105/R107/R124 |
| 7 | Android + backend idempotency regressions | R116/R118..R122 |

WorkManager bounds (network constraint, exponential backoff, `MAX_SYNC_ATTEMPTS`)
and orphan-SYNCING recovery (`getPendingOrFailed` includes SYNCING) were already
present and are preserved (R115/R117).

## 7. Risks & rollback

- **Risk:** an over-broad classifier could queue a canonical rejection offline.
  *Mitigation:* the classifier is allow-listed and fail-closed; only *thrown*
  transport exceptions are eligible, and any received HTTP response (even 5xx)
  maps to `Rejected` (server was reachable). Unit tests pin every branch.
- **Risk:** duplicate offline rows on rapid taps. *Mitigation:* the ViewModel
  re-entry guard + the unique `clientReference` index + `findByClientReference`
  reconciliation guarantee at most one row.
- **Rollback:** the change is additive Android source + tests + one test-only
  Gradle dependency; reverting the branch restores prior behaviour with no schema
  or backend change. No Room migration is introduced (the entity/columns are
  unchanged), so there is no forward/backward data-migration risk.

## 8. Physical revalidation boundary

This is source remediation with automated proof. The historical R11 stays FAIL
for the old APK/anchor, and a fresh physical-device R11 campaign on the future
frozen final APK remains mandatory (UIX8C-R129/R130). UIX-7 stays `NO-GO — GO
DEFERRED`; UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`.
