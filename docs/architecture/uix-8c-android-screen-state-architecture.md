# UIX-8C — Android Screen, State & Dependency Architecture

Sprint: **UIX-8C-01**. Rule set: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
(UIX8C-R001..R030). Package: `com.aishtech.poslite`. minSdk 26 / targetSdk 35 /
JDK 21. This is an architecture + inventory document only — no runtime code
changes in UIX-8C-01.

## 1. Dependency graph (targeted fallback)

Graphify MCP was not connected in this environment; the graph below was derived
by a targeted static scan of `android/app/src/main/java/com/aishtech/poslite`
(`find` + `rg`), which is the documented fallback (prompt "Graphify" clause).

### 1.1 Functional flow

```
Activation/Login (LoginActivity + LoginViewModel + AuthRepository/DeviceRepository)
  -> authenticated context (SessionManager, TokenStore, DeviceIdentityStore, AndroidRuntimeState)
  -> cashier home (CashierActivity + CashierViewModel)
     -> products / search / categories (CatalogRepository -> ProductDao/ProductCategoryDao, CatalogSyncManager)
     -> cart (CartRepository + CartItem, RupiahMoney integer money)
     -> payment (PaymentSheetFragment -> CashierViewModel -> SalesRepository)
        -> offline persistence (OfflineSaleRepository -> OfflineSaleDao/OfflineSaleItemDao -> PosDatabase/Room)
        -> WorkManager (OfflineSalesSyncScheduler -> OfflineSalesSyncWorker -> OfflineSyncBatchFactory)
        -> backend idempotency (PosApiService via clientReference; ApiClient/AuthInterceptor/DeviceHeaderInterceptor)
     -> receipt (ReceiptActivity + ReceiptViewModel -> ReceiptRepository)
  -> history (TransactionHistoryActivity + TransactionHistoryViewModel; SyncStatusDisplay)
  -> printer (PrinterRepository -> BluetoothPrinterConnection/EscPosReceiptFormatter; presentation-only, rule 58)
  -> QRIS (QrisPaymentActivity + QrisPaymentViewModel -> QrisRepository; QrisOnlineOnlyGuard, online-only, hidden until lifecycle complete)
```

### 1.2 Layer map (XML -> backend)

```
XML/layout            Fragment/Activity                 ViewModel                     Repository                 Room / Retrofit / WorkManager        Backend API
--------------------  --------------------------------  ----------------------------  ------------------------  -----------------------------------  ------------------------------
activity_login        LoginActivity                     LoginViewModel                AuthRepository            PosApiService (Retrofit)             POST /api/v1/... auth + device
                      (MainActivity = splash/router)                                  DeviceRepository          DeviceIdentityStore                  device activation
activity_cashier      CashierActivity                   CashierViewModel              CatalogRepository         ProductDao/ProductCategoryDao (Room) product sync
  item_product        ProductListAdapter                                             CartRepository            CatalogSyncManager                   GET products/categories
view_payment_sheet    PaymentSheetFragment              CashierViewModel             SalesRepository           OfflineSaleDao/OfflineSaleItemDao    POST sale (idempotent clientReference)
                                                                                      OfflineSaleRepository     OfflineSalesSyncWorker (WorkManager) sync offline sales
activity_receipt      ReceiptActivity                   ReceiptViewModel             ReceiptRepository         PosApiService                        GET receipt
activity_transaction  TransactionHistoryActivity        TransactionHistoryViewModel  SalesRepository           OfflineSaleDao                       GET transactions
  item_transaction                                                                    OfflineSaleRepository
activity_qris_payment QrisPaymentActivity               QrisPaymentViewModel         QrisRepository            PosApiService (QrisOnlineOnlyGuard)  QRIS intent (online-only)
activity_reports      ReportsActivity                   ReportsViewModel             ReportRepository/Closing  PosApiService                        reports/closing
activity_subscription SubscriptionStatusActivity        SubscriptionStatusViewModel  SubscriptionRepository    PosApiService                        subscription status
(printer surface)     (PrinterSettingsStore)                                          PrinterRepository         BluetoothPrinterConnection           n/a (device transport)
```

Canonical cross-cutting: `core/money/RupiahMoney` (integer money), `core/session/*`
(auth), `core/device/*` (device identity), `core/network/*` (Retrofit + auth/device
interceptors + `NetworkMonitor`), `core/util/Event` (one-time events),
`core/util/ResultState` (state), `core/ServiceLocator` (wiring).

## 2. Full screen & state inventory

Legend — **Surface**: current Activity/Fragment that hosts it. **State**:
loading / empty / error / offline / success (UIX8C-R006). **Owner**: the UIX-8C
sub-sprint that rebuilds it (see delivery plan). Screens listed as "state"
are ViewModel-driven states of a host surface, not separate Activities.

### 2.1 Authentication / device
- **Splash** — MainActivity (router). States: loading, error, success. Owner: UIX-8C-03.
- **Activation** — LoginActivity (device activation). States: loading, error, offline, success. Owner: UIX-8C-03.
- **Login** — LoginActivity. States: loading, error, offline, success. Owner: UIX-8C-03.
- **Expired session** — LoginActivity/SessionManager. State: error/session. Owner: UIX-8C-03.
- **Activation failure** — LoginActivity. State: error. Owner: UIX-8C-03.
- **Device unavailable** — LoginActivity/AndroidRuntimeState. State: error/offline. Owner: UIX-8C-03.
- **Logout / account switch** — SessionManager (cross-tenant clear, UIX8C-R011). State: success. Owner: UIX-8C-03.

### 2.2 Cashier
- **Home** — CashierActivity. States: loading, empty, error, offline, success. Owner: UIX-8C-04.
- **Context header** (tenant/outlet/cashier/device/network/sync) — CashierActivity (R01 remediation). Owner: UIX-8C-04.
- **Products** — CashierActivity + ProductListAdapter. States: loading, empty, success. Owner: UIX-8C-04.
- **Search** — CashierViewModel (must not mutate cart, UIX8C-R007). State: loading, no-match. Owner: UIX-8C-04.
- **Categories** — CashierViewModel. State: success/empty. Owner: UIX-8C-04.
- **Cart** — CartRepository/CartItem. States: loading, empty, success. Owner: UIX-8C-05.
- **Empty cart** — CashierActivity. State: empty. Owner: UIX-8C-05.
- **Loading** (catalog) — CashierActivity. State: loading (cart preserved, UIX8C-R014). Owner: UIX-8C-04.
- **Empty catalog** — CashierActivity. State: empty. Owner: UIX-8C-04.
- **No-match** (search) — CashierActivity. State: empty. Owner: UIX-8C-04.
- **Unavailable / error** — CashierActivity. State: error ("Tidak tersedia"). Owner: UIX-8C-04.
- **Cached / offline catalog** — CatalogRepository (Room). State: offline. Owner: UIX-8C-04.

### 2.3 Payment
- **Cash payment sheet** — PaymentSheetFragment. States: loading, error, success. Owner: UIX-8C-06.
- **Quick tender** — PaymentSheetFragment. State: success. Owner: UIX-8C-06.
- **Manual tender** — PaymentSheetFragment (`RupiahMoney.parse`, UIX8C-R009). State: success/error. Owner: UIX-8C-06.
- **Insufficient cash** — PaymentSheetFragment. State: error. Owner: UIX-8C-06.
- **Submitting** — CashierViewModel (double-submit guard, UIX8C-R015). State: loading. Owner: UIX-8C-06.
- **Online success** — CashierViewModel (server ack). State: success. Owner: UIX-8C-06.
- **Offline queued** — OfflineSaleRepository (durable save before cart clear, R11 remediation, UIX8C-R012/R014). State: offline. Owner: UIX-8C-06.
- **Canonical server rejection** — CashierViewModel (never becomes offline success, UIX8C-R013). State: error. Owner: UIX-8C-06.

### 2.4 Sync
- **Pending** — OfflineSyncStatus. State: offline/pending. Owner: UIX-8C-07.
- **Syncing** — OfflineSalesSyncWorker. State: loading. Owner: UIX-8C-07.
- **Synced** — SyncStatusDisplay (only on server ack). State: success. Owner: UIX-8C-07.
- **Retrying** — OfflineSaleRepository (bounded, MAX_SYNC_ATTEMPTS). State: loading. Owner: UIX-8C-07.
- **Failed** — OfflineSaleRepository (visible, not dropped). State: error. Owner: UIX-8C-07.
- **Conflict** — sync path. State: error. Owner: UIX-8C-07.
- **Reconnect** — NetworkMonitor + scheduler (idempotent, UIX8C-R015). State: offline->success. Owner: UIX-8C-07.
- **Orphan-SYNCING recovery** — OfflineSaleRepository. State: error->recover. Owner: UIX-8C-07.

### 2.5 Receipt / history
- **Current receipt** — ReceiptActivity (binds current txn). State: success. Owner: UIX-8C-08.
- **Offline receipt** — ReceiptActivity. State: offline. Owner: UIX-8C-08.
- **Synced receipt** — ReceiptActivity. State: success. Owner: UIX-8C-08.
- **Transaction history** — TransactionHistoryActivity. States: loading, empty, error, success. Owner: UIX-8C-08.
- **Empty history** — TransactionHistoryActivity. State: empty. Owner: UIX-8C-08.
- **Pending history** — TransactionHistoryActivity. State: offline/pending. Owner: UIX-8C-08.
- **Failed history** — TransactionHistoryActivity. State: error. Owner: UIX-8C-08.
- **Transaction detail** — TransactionHistoryActivity. State: success. Owner: UIX-8C-08.

### 2.6 Settings / device / printer
- **Cashier identity** — settings surface (UIX8C-R010). State: success. Owner: UIX-8C-09.
- **Tenant / outlet** — settings surface. State: success. Owner: UIX-8C-09.
- **Device status** — AndroidRuntimeState. State: success/error. Owner: UIX-8C-09.
- **App version** — AppConfig. State: success. Owner: UIX-8C-09.
- **Network / sync status** — NetworkMonitor + SyncStatusDisplay. State: offline/success. Owner: UIX-8C-09.
- **Printer status** — PrinterRepository/BluetoothPrinterConnection (rule 58, presentation-only). State: error/success. Owner: UIX-8C-09.
- **Logout** — SessionManager. State: success. Owner: UIX-8C-09.

## 3. Target premium architecture (UIX-8C)

- **Single authoritative ViewModel state holder per screen** (UIX8C-R007); one
  sealed UI-state per surface (`ResultState`) folding loading/empty/error/
  offline/success; one-time effects via `Event` (no replay after recreation).
- **Centralized Material 3 design system** (UIX8C-R017/R018): tokens in
  `res/values/*`, reusable `Widget.Aish.*` / `TextAppearance.Aish.*`; zero raw
  hex/spacing in new layouts; brand gradient limited to header/CTA/success.
- **Integer-exact money everywhere** (UIX8C-R009): `RupiahMoney` (`Long`);
  legacy Double only at the single documented storage/DTO boundary.
- **Governed offline persistence** (UIX8C-R012/R013/R014): durable Room save
  before cart clear; transport-failure-only fallback; canonical HTTP rejection
  is an error, never a queued success; stable `clientReference` (UIX8C-R015).
- **Truthful identity + state** (UIX8C-R010/R022/R023): server-resolved
  tenant/outlet/cashier/device context header; status never colour-only; long
  names never break layout.
- **Accessibility as a release gate** (UIX8C-R019/R020/R021): >=48dp targets,
  TalkBack/focus/labels, 130% font operability.
- **Canonical services stay authoritative** (UIX8C-R008): screens present and
  orchestrate; no second pricing/payment/QRIS/settlement/sync engine.

## 4. Remediation binding (from failed run `run-97fbb64-2af94aa`)

| Finding | Status | Owner sprint | Target rule |
| --- | --- | --- | --- |
| R01 identity not visible | PENDING | UIX-8C-04 (context header) | UIX8C-R010 |
| R11 offline CASH not durable | FAIL | UIX-8C-06 (payment/offline) | UIX8C-R012/R014 |
| R18 layout collapse at 130% font | FAIL | UIX-8C-09 (accessibility) | UIX8C-R021 |

These bindings are planning only; UIX-8C-01 does not fix them (scope guard,
rule 61). The failed run stays immutable (UIX8C-R003).
