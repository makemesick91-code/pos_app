# UIX-8C-05 — Payment / Offline-Queue / Sync-Recovery Test Matrix

- Sprint: UIX-8C-05
- Android tests: `android/app/src/test/java/com/aishtech/poslite/`
- Backend fence: `backend/tests/Feature/PaymentSyncUxIdempotencyRegressionTest.php`

## Purpose

Map every UIX-8C-05 scenario to its automated test and the UIX8C-R1xx rule it
enforces. Automated + emulator evidence is **development evidence only** and never
replaces physical-device runtime closure (UIX8C-R129; rules 55/56/59). Historical
physical run `run-97fbb64-2af94aa` (R11 FAIL, R18 FAIL, R01 PENDING) stays immutable.

## Matrix

| # | Scenario | Test | Rule(s) |
|---|----------|------|---------|
| 1 | Money parser: garbage/overflow → null, never fabricated 0 | `TenderValidatorTest` | R136 |
| 2 | Quick tender: strictly-above-due, distinct/sorted, ≤3, overflow-safe | `QuickTenderCalculatorTest` | R137 |
| 3 | Validation: Empty/Invalid/Insufficient/Valid; change never negative | `TenderValidatorTest` | R135, R136 |
| 4 | `canSubmit` only for Valid; insufficient/invalid never submit | `TenderValidatorTest` | R138 |
| 5 | Double-submit guard (ViewModel re-entry) | `CashierCheckoutFallbackTest` | R143 |
| 6 | Online success only on server ack | `PaymentUiStateMapperTest`, `PaymentSyncRecoveryViewModelTest` | R144 |
| 7 | Offline queued only after durable commit; `OfflineSaved`→`OfflineQueued` not `Synced` | `PaymentUiStateMapperTest` | R145, R147 |
| 8 | State machine: allowed transitions + fail-closed invalid transitions | `PaymentUiStateMapperTest` | R146, R150 |
| 9 | `SYNCED` only from recorded SYNCED ack; FAILED under/at cap; CONFLICT; unknown→fail-closed | `PaymentUiStateMapperTest`, `SyncRecoveryPresenterTest` | R148, R156 |
| 10 | Process restoration: same pending transaction + `clientReference` | `CashierCheckoutFallbackTest`, `PaymentSyncRecoveryViewModelTest` | R151, R152 |
| 11 | Manual retry: reuses transaction/`clientReference`, bounded, worker-coordinated | `SyncRecoveryPresenterTest`, `PaymentSyncRecoveryViewModelTest` | R157, R158, R159 |
| 12 | `canManualRetry` never for CONFLICT / poison-at-cap / PENDING/SYNCING/SYNCED | `SyncRecoveryPresenterTest` | R159, R160 |
| 13 | Reconnect: one-shot event, refreshes counts, creates no new work | `PaymentSyncRecoveryViewModelTest` | R158 |
| 14 | Transport fallback eligible; TLS never offline | `CashierCheckoutFallbackTest` | R149 |
| 15 | Accessibility labels, live region, ≥48dp, focus order | `PaymentSheetLayoutTest` | R162–R166 |
| 16 | 100 / 115 / 130% font: confirm CTA scroll-reachable | `PaymentSheetLayoutTest` | R167 |
| 17 | Backend idempotency: one sale/payment/item-set per `(tenant, store, client_reference)` | `PaymentSyncUxIdempotencyRegressionTest.php` | R168 |

## Reused UIX-8C-04 coverage

`CashierCheckoutFallbackTest` (process recreation, double-submit, transport
fallback, TLS-never-offline) continues to fence the reused foundation; UIX-8C-05
adds no new checkout/persistence/WorkManager/backend sale path.

## Evidence posture

All rows above are unit/JVM or emulator-class automated evidence. A fresh physical
R11 + payment/sync UX revalidation on the frozen final APK is mandatory after code
freeze; UIX-7 and UIX-8 remain GO deferred until then.
