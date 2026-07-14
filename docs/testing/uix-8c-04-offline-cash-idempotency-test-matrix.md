# UIX-8C-04 — Offline CASH Durability & Idempotency Test Matrix

Automated regression coverage for UIX8C-R096..R130. All rows are executable JVM /
PHPUnit tests (no fabricated evidence). Physical-device rows remain deferred to
the post-freeze campaign (UIX8C-R129).

## Android — transport classification (`TransportFailureClassifierTest`)

| Scenario | Expect | Rule |
|----------|--------|------|
| UnknownHostException (DNS) | Eligible | R098 |
| SocketTimeoutException | Eligible | R098 |
| ConnectException (refused) | Eligible | R098 |
| NoRouteToHostException | Eligible | R098 |
| PortUnreachableException | Eligible | R098 |
| SocketException (reset) | Eligible | R098 |
| InterruptedIOException | Eligible | R098 |
| SSLHandshakeException | Ineligible | R103 |
| SSLPeerUnverifiedException | Ineligible | R103 |
| generic IOException | Ineligible (fail-closed) | R103 |
| IllegalState / NPE / runtime | Ineligible | R103 |
| wrapped UnknownHost in cause chain | Eligible | R098 |
| wrapped TLS in cause chain | Ineligible | R103 |

## Android — online submit outcome (`SalesRepositoryTest`)

| Scenario | Expect | Rule |
|----------|--------|------|
| 2xx success carries sale + ANDROID_ONLINE reference | Success | R097 |
| HTTP 422 | Rejected (never offline) | R099/R102 |
| HTTP 403 | Rejected (never offline) | R101 |
| thrown UnknownHost | TransportUnavailable | R098 |
| thrown SSLHandshake | Failed (never offline) | R103 |

## Android — durable offline persistence (`OfflineSaleRepositoryTest`)

| Scenario | Expect | Rule |
|----------|--------|------|
| supplied clientReference reused (not regenerated) | Saved with same ref | R097 |
| repeated fallback, same reference | one row, same localId | R109 |
| item snapshots persisted, whole-Rupiah exact | 2 items, exact totals | R106/R121 |
| empty cart / insufficient tender | Error, nothing stored | R104/R108 |
| cart cleared only after a successful save | success clears, failure keeps | R107/R108 |

## Android — governed fallback (`CashierCheckoutFallbackTest`, ViewModel)

| Scenario | Expect | Rule |
|----------|--------|------|
| transport failure → durable offline save, cart cleared, one PENDING row | OfflineSaved | R098/R105/R107 |
| online attempt + offline row share one stable reference | equal refs | R097 |
| canonical rejection (422) keeps cart, never queues offline | Error, 0 rows, cart kept | R099/R108 |
| TLS failure keeps cart, never queues offline | Error, 0 rows, cart kept | R103/R108 |
| rapid double tap while in flight | one row, one attempt | R109 |
| durable row survives process recreation (fresh repo, same store) | pending row + ref present | R112/R113 |

## Backend — idempotent replay (`OfflineCashDurabilityIdempotencyTest`)

| Scenario | Expect | Rule |
|----------|--------|------|
| stable reference replayed 2× (reconnect + worker) | 1 sale, 1 payment, 1 item-set, inventory unchanged | R116/R119..R122 |
| timeout-after-commit retry | reconciles to same sale, count 1 | R118 |
| cross-tenant reference reuse | isolated, 2 distinct sales | R118 |
| offline payload with foreign product | 422, 0 sales | R099/R101 |

Existing `SalesIdempotencyTest`, `InventoryIdempotencyTest`, and
`OfflineSalesTenantIsolationTest` remain the broader idempotency/tenant fence.

## Fail-closed gate (`uix8c_offline_cash_durability_gate.sh` + self-tests)

The gate asserts rule persistence (R096..R130), the docs, the typed classifier,
the prohibition of a catch-all offline fallback, QRIS-offline prohibition, the
atomic Room path, cart-clear-after-save, stable clientReference, bounded retry,
orphan recovery, Android + backend idempotency tests, no float money on the
offline path, the immutable failed run, UIX-7/UIX-8 deferred, and no premature
GO. Its self-tests prove it fails closed on: a missing rule, a catch-all
fallback, QRIS-offline, cart-clear-before-save, a missing stable reference, a
missing bounded retry, a missing idempotency test, a mutated historical run, a
premature UIX-7/UIX-8 GO, and a secret value.

## Deferred (physical, post-freeze — NOT run here)

Real-device DNS-off CASH checkout, force-stop + relaunch recovery, airplane-mode
reconnect sync, and duplicate-transaction inspection on the frozen final APK
(UIX8C-R129). Historical R11 stays FAIL until then.
