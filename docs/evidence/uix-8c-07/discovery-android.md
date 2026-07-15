# UIX-8C-07 Discovery — Android Architecture & Contract Map

DISCOVERY ONLY (no code changed). Scope: Premium Authentication, Device Activation,
Settings & Session Recovery for the native cashier app `com.aishtech.poslite`
(Views/XML + Retrofit/OkHttp + Room + WorkManager + ViewModel/LiveData).

Package root: `app/src/main/java/com/aishtech/poslite/` (abbreviated `…/` below).
Build config: `compileSdk/targetSdk 35`, `minSdk 26`, `versionName 0.1.0`, Java 17,
no DI framework (manual `ServiceLocator`), Room v2 (`fallbackToDestructiveMigration`).

---

## 1. Startup, Application class & navigation

- **No custom `Application` subclass** (manifest `<application>` has no `android:name`;
  `grep Application()` → NONE). All wiring is the singleton `object ServiceLocator`
  (`core/ServiceLocator.kt`), lazily built from `applicationContext`.
- **Launcher** = `MainActivity` (`MainActivity.kt`, `exported=true`, the only exported
  component). It is a pure router with no UI:
  ```kotlin
  val target = if (ServiceLocator.session(applicationContext).isLoggedIn())
      CashierActivity::class.java else LoginActivity::class.java
  startActivity(Intent(this, target)); finish()
  ```
- **Start-destination decision** = presence of a non-blank Sanctum token only
  (`SessionManager.isLoggedIn()` → `TokenStore`). **There is NO state machine**, no
  device-activation gate, no runtime-policy gate, no pending-sync gate in routing.
  Activation/subscription checks happen *inside* `LoginViewModel` after credential auth,
  not at cold-start routing.
- Activities (all `exported=false` except MainActivity): `LoginActivity`,
  `CashierActivity`, `QrisPaymentActivity`, `ReceiptActivity`, `ReportsActivity`,
  `SubscriptionStatusActivity`, `TransactionHistoryActivity`. **No SettingsActivity.**

## 2. Authentication & token storage

- **Login UI**: `feature/auth/LoginActivity.kt` (`ActivityLoginBinding`, email/password/
  progress/error) → `feature/auth/LoginViewModel.kt`.
- `LoginViewModel(authRepository, subscriptionRepository, deviceRepository)`:
  - `fun login(email, password)`; states `UiState.{Idle,Loading,Blocked(msg),Error(msg)}`;
    one-shot nav `LiveData<Event<Nav>>` where `enum Nav { CASHIER, SUBSCRIPTION }`.
  - **Bootstrap sequence** after credential success: `subscriptionRepository.getStatus()`
    → if blocked → `Blocked` + `Nav.SUBSCRIPTION`; else `deviceRepository.registerCurrentDevice()`
    → success → `Nav.CASHIER`; device rejection → `Blocked`. Cashier is entered only when
    subscription allowed AND device registered.
- `data/repository/AuthRepository.kt` (`AuthRepository(api, session)`):
  - `suspend fun login(email, password): ResultState<LoginResponse>` — on success calls
    `session.startSession(body.token)`; 422 → "Email atau kata sandi salah."
  - `suspend fun me(): ResultState<MeResponse>` — canonical context source (`GET auth/me`).
  - `suspend fun logout()` — best-effort `api.logout()`, **always** `session.endSession()`.
  - `fun isLoggedIn(): Boolean`.
- **Token storage** = plain `SharedPreferences` (`core/session/TokenStore.kt`):
  `SharedPrefsTokenStore` — prefs `"aish_pos_session"`, key `"auth_token"`.
  **NOT EncryptedSharedPreferences / Keystore** — carries an explicit
  `TODO(secure-storage)`. `interface TokenStore { saveToken/getToken/clearToken/isLoggedIn }`.
  `core/session/SessionManager.kt` = thin facade (`startSession/token/isLoggedIn/endSession`).
- **HTTP auth**: `core/network/AuthInterceptor.kt` attaches `Authorization: Bearer <token>`
  + `Accept: application/json` when a token exists. `core/network/DeviceHeaderInterceptor.kt`
  attaches `X-Device-UUID` when an identity exists (`HEADER_DEVICE_UUID`).
- **401 handling: NONE.** There is no OkHttp `Authenticator`, no interceptor that clears the
  session or forces re-login on 401. A 401 surfaces per-call as a `ResultState.Error`/`Rejected`
  string only. **This is the core session-recovery gap UIX-8C-07 must close.**
- `core/network/ApiClient.kt`: builds Retrofit; `HttpLoggingInterceptor` (BASIC, redacts
  `Authorization`) attached **only when `BuildConfig.BUILD_TYPE == "debug"`** (not on
  debuggable `pilot`). Timeouts: connect 15s / read 20s.

## 3. Device activation / registration

Two DISTINCT backend device mechanisms exist; only the Sprint-10 one is wired to UI.

- **Sprint 10 registration (WIRED, used at login)**: `data/repository/DeviceRepository.kt`
  `DeviceRepository(api, identityStore)`:
  - `suspend fun registerCurrentDevice(storeId: Long? = null): ResultState<RegisteredDeviceResult>`
    (`POST devices/register`; 403/402 → blocked, never success).
  - `suspend fun heartbeat(): ResultState<RegisteredDeviceResult>` (`POST devices/heartbeat`).
  - `suspend fun listDevices(status): ResultState<List<RegisteredDeviceDto>>` (`GET devices`).
  - `data class RegisteredDeviceResult(device: RegisteredDeviceDto, existingDevice: Boolean)`.
  - DTOs (`data/remote/dto/DeviceDtos.kt`): `RegisterDeviceRequestDto(device_uuid, device_name,
    platform, app_version, store_id)`, `DeviceHeartbeatRequestDto`, `RegisteredDeviceResponseDto{data,meta}`,
    `RegisteredDeviceDto(id, device_uuid, device_name, platform, status, registered_at, last_seen_at)`.
- **Sprint 34 activation-code flow (PRESENT but NO UI CALLS IT)**: `PosApiService.activateDevice`,
  `androidDeviceHeartbeat`, `getAndroidRuntimePolicy` endpoints exist; builder
  `core/runtime/DeviceActivationRequestFactory.kt` (`DeviceActivationInput(activationToken,
  deviceFingerprint, deviceUuid, deviceLabel?)` with redacted `toString()`,
  `DeviceActivationRequestFactory.build(...)`); DTOs in `data/remote/dto/AndroidRuntimeDtos.kt`
  (`ActivateDeviceRequestDto`, `DeviceActivationResponseDto/Dto`, `AndroidRuntimePolicyDto`
  {offline/sync/runtime/staleBehavior}, `SyncBatchRequestDto/…`). **No repository, ViewModel, or
  Activity currently invokes `activateDevice`/`getAndroidRuntimePolicy`.** This is the likely
  greenfield surface for a premium activation screen.
- **Device identity** (`core/device/DeviceIdentityStore.kt`): opaque random UUID generated once,
  stored in `SharedPreferences "aish_pos_device"` key `"device_uuid"`; `getOrCreateDeviceUuid()`,
  `currentDeviceUuid()` (non-creating, feeds interceptor), `clear()`. `fun interface
  DeviceUuidProvider`. `core/device/DeviceInfoProvider.kt`: `deviceName()`, `appVersion()`,
  `PLATFORM_ANDROID`.

## 4. Runtime / tenant context

- `feature/cashier/CashierContext.kt`:
  - `data class CashierContext(businessName, outletName, cashierName, roleLabel, deviceName,
    online: Boolean)` with derived `cashierLine`.
  - `object CashierContextPresenter` (pure/JVM-testable): `UNAVAILABLE = "Tidak tersedia"`;
    `fun present(me: MeResponse?, deviceName: String, reachable: Boolean): CashierContext`.
    `online = reachable && me != null` (online ≠ merely connected).
- Context is sourced ONLY from `GET auth/me` (`AuthRepository.me()` → `MeResponse{user,tenant,store}`,
  DTOs in `data/remote/dto/AuthDtos.kt`: `UserDto(id,name,email,role,tenant_id,store_id)`,
  `TenantDto(id,name,status)`, `StoreDto(id,name,code)`). Loaded via `CashierViewModel.loadContext()`.
- **There is no standalone `RuntimeContext`/session holder object.** Tenant/outlet/cashier identity
  lives transiently in `MeResponse` and `CashierContext`; device identity in `DeviceIdentityStore`;
  token in `SessionManager`. `core/runtime/AndroidRuntimeState.kt` models the *server posture*
  (`RuntimeStatus`, `AndroidRuntimePosture{writeAllowed,readOnly,failSafe()}`, `AndroidRuntimeMessages`)
  but **is not currently fetched or enforced anywhere** (no caller of `getAndroidRuntimePolicy`).

## 5. Room database

- `core/database/PosDatabase.kt`: `@Database(version=2)` entities `LocalProductEntity`,
  `LocalProductCategoryEntity`, `AppSettingEntity`, `LocalOfflineSaleEntity`,
  `LocalOfflineSaleItemEntity`; DAOs `productDao/productCategoryDao/appSettingDao/
  offlineSaleDao/offlineSaleItemDao`; DB file `"aish_pos_catalog.db"`;
  `fallbackToDestructiveMigration()`. **Adding an entity/column bumps `version` and needs a
  migration or accepts destructive fallback.**
- Offline queue: `data/local/entity/LocalOfflineSaleEntity.kt` (table `offline_sales`, **unique
  index on `clientReference`**, index on `syncStatus`; Double money columns; fields incl.
  `syncStatus, syncAttemptCount, lastSyncError, serverSaleId, serverInvoiceNumber, createdAt`),
  `LocalOfflineSaleItemEntity.kt` (`offline_sale_items`). `data/local/OfflineSyncStatus.kt`:
  string consts `PENDING/SYNCING/SYNCED/FAILED/CONFLICT`.
- `data/local/dao/OfflineSaleDao.kt` (abstract class) key signatures the new work reuses:
  - `insertOfflineSaleWithItems(sale, items): Long` (atomic).
  - `getPendingOrFailed(limit, maxAttempts): List<…>` (PENDING+SYNCING+FAILED-under-cap).
  - `findByClientReference(clientReference): LocalOfflineSaleEntity?`.
  - `markSyncing/markSynced/markFailed/markConflict(...)`.
  - **`countPending(): Int` (PENDING+SYNCING)** and **`countFailed(): Int`** — the
    "pending unsynced transaction count" for a logout/session-recovery guard.
  - `getRecent(limit): List<…>`.
- `data/repository/OfflineSaleRepository.kt` (`OfflineSaleRepository(dao,itemDao,api,
  referenceProvider,clock)`, implements `LocalReceiptSource`):
  - `suspend fun createOfflineCashSale(items, paidAmount: Long, storeId?, clientReference?): SaveResult`
    (`SaveResult.{Saved(localId,clientReference),Error(msg)}`); idempotent on `clientReference`;
    cart never cleared here.
  - `suspend fun syncPending(limit=10): SyncSummary`; `enum SyncOutcome{SYNCED,FAILED,CONFLICT}`.
  - **`suspend fun pendingCount(): Int`**, **`suspend fun failedCount(): Int`**.
  - `suspend fun recentSales(limit=100): List<LocalOfflineSaleEntity>`.
  - `findSaleWithItems(localId)`, `findSaleWithItemsByReference(clientReference)` →
    `LocalSaleWithItems(sale, items)`.
  - `companion { SOURCE_ANDROID_OFFLINE="ANDROID_OFFLINE"; MAX_SYNC_ATTEMPTS=5 }`.
- Receipt/history projection (UIX-8C-06, pure/JVM): `feature/receipt/ReceiptProjection.kt`
  (`ReceiptProjection`, `ReceiptIdentity{clientReference?,serverSaleId?,localId?} matches()`,
  `enum ReceiptTransactionState{ONLINE_SUCCESS,OFFLINE_PENDING,SYNCING,SYNCED,FAILED,CONFLICT}`,
  `ReceiptLine`), `feature/receipt/ReceiptProjector.kt` (`object`: `fromLocalSale(...)`,
  `fromServerReceipt(...)`, `stateFromSyncStatus(status)`; server decimal strings parsed via
  `substringBefore('.')`, NOT `RupiahMoney.parse`), `feature/receipt/ReceiptSources.kt`
  (`interface ServerReceiptSource.getReceipt(saleId)`, `interface LocalReceiptSource`).
  History: `feature/history/TransactionHistoryModels.kt` (`HistoryRecord{mergeKey}`, `HistoryRow`,
  `enum HistoryDisplayState`, `enum HistorySource`), `TransactionHistoryReconciler.kt`
  (`object.reconcile(...)` → one row per logical tx keyed on `clientReference`).

## 6. Sync worker & payment state

- `feature/sync/OfflineSalesSyncScheduler.kt` (`object`): `enqueue(context)` unique work
  `UNIQUE_WORK_NAME="offline-sales-sync"`, `ExistingWorkPolicy.KEEP`, `NetworkType.CONNECTED`,
  exponential backoff 30s.
- `feature/sync/OfflineSalesSyncWorker.kt` (`CoroutineWorker`): `doWork()` → `repository
  .syncPending(BATCH_SIZE=10)`; failures → `Result.retry()`; never crashes.
- `clientReference` lifecycle: minted once per cart in `CashierViewModel.checkoutReference()`
  (`pendingCheckoutReference`), reused across online submit / offline fallback / retry / restart;
  reset on durable success or cart mutation.
- `feature/cashier/PaymentUiState.kt` (sealed): `Idle, EditingTender, Ready, SubmittingOnline,
  PersistingOffline, OnlineSuccess(sale), OfflineQueued(clientReference,grandTotal,change),
  Pending, Syncing, RetryScheduled, Failed(message,retryable), Conflict(clientReference), Synced`.
  `feature/cashier/PaymentUiStateMapper.kt` (`object`): `fromCheckout(CheckoutState)`,
  `fromSyncStatus(status,attempts,cap)`, `isAllowedTransition(from,to)` — SYNCED only from
  canonical SYNCED. `feature/sync/SyncRecoveryPresenter.kt` (`object.present(status,attempts,cap)`,
  `canManualRetry(...)`).
- `CashierViewModel` checkout API: `checkoutCash(paidAmount)` (online→governed offline fallback,
  double-submit guard `if (_checkout.value is Submitting) return`), `checkoutCashOffline`,
  `syncNow()`, `requestManualRetry()=syncNow()`, `onConnectivityRestored()`, `refreshSyncCounts()`,
  `resetCheckout()`; `CheckoutState.{Idle,Submitting,Success(sale),OfflineSaved(clientReference,
  grandTotal,change),Error(msg)}`; `SyncCounts(pending,failed)`; `paymentUiState: LiveData<PaymentUiState>`.

## 7. Printer subsystem

- `feature/printer/PrinterState.kt`: `enum PrinterFailure{PERMISSION_REQUIRED,PERMISSION_DENIED,
  UNSUPPORTED,ADAPTER_DISABLED,DEVICE_NOT_CONFIGURED,DEVICE_UNAVAILABLE,CONNECTION_FAILED,TIMEOUT,
  WRITE_FAILED,INTERRUPTED,NOT_PRINTABLE,UNKNOWN_SAFE_FAILURE}`; `sealed PrintOutcome{Printed,
  AlreadyPrinting,Failed(reason,message,retryable)}`.
- `feature/printer/PrinterCoordinator.kt` (`PrinterCoordinator(printer: ReceiptPrinter)`):
  `suspend fun print(receipt: ReceiptDto): PrintOutcome` with `AtomicBoolean` single-flight guard;
  **no reference to any sale/payment/sync/inventory repo** (non-financial invariant — regression-sensitive).
- `interface ReceiptPrinter.printReceipt(receipt): PrintOutcome`; `PrinterRepository(connection,
  settingsStore,formatter)` implements it (`isRetryable(reason)`, `preview()`).
- `feature/printer/PrinterConnection.kt`: `interface PrinterConnection.print(macAddress,payload):
  PrintResult`; `sealed PrintResult{Success, Failure(reason,message)}`.
- `feature/printer/BluetoothPrinterConnection.kt`: RFCOMM to a *paired* MAC, **no discovery, no
  `BLUETOOTH_SCAN`**; `hasConnectPermission()` gates `BLUETOOTH_CONNECT` (runtime API 31+, legacy
  `BLUETOOTH` ≤ API 30); `SecurityException` → typed `PERMISSION_DENIED` failure.
- `feature/printer/PrinterSettingsStore.kt`: `SharedPreferences "aish_pos_printer"`;
  `PrinterSettings(printerName,printerMacAddress,paperWidthMm=58,autoCutEnabled)`,
  `load()/save()/clear()`. **A Settings screen would edit this store** (candidate change surface).

## 8. Settings, logout & account-switch (KEY GAP)

- **There is NO Settings screen today.** Printer config is persisted (`PrinterSettingsStore`) but
  has no editor UI.
- **Logout exists only in `SubscriptionStatusActivity.logout()`** (back button) → `AuthRepository
  .logout()` → LoginActivity with `CLEAR_TOP|NEW_TASK`. **`CashierActivity` has no logout.**
- **What logout clears today: ONLY the Sanctum token** (`session.endSession()` →
  `SharedPrefsTokenStore.clearToken()`). It does **NOT** clear: the device UUID
  (`aish_pos_device`), the Room DB (`aish_pos_catalog.db` incl. **unsynced offline sales**),
  the catalog/sync cursors (`app_settings`), printer settings (`aish_pos_printer`), or WorkManager
  queue. There is **no pending-unsynced guard** before logout. This is the central UIX-8C-07
  concern (UIX7-R016/R017/UIX8C-R011/R126: account/device switch must re-scope local data and must
  not silently discard unsynced transactions — `pendingCount()>0` must block/warn).

## 9. Test infrastructure

- Frameworks in use (from `app/build.gradle.kts` + imports): **JUnit4** only, plus
  `kotlinx-coroutines-test` (`runTest`, `StandardTestDispatcher`, `Dispatchers.setMain/resetMain`,
  `advanceUntilIdle`) and `androidx.arch.core:core-testing` **`InstantTaskExecutorRule`** for LiveData.
  **No Mockito, no MockK, no Robolectric, no Turbine.** `androidTest` only has default espresso/ext
  deps and no instrumented tests written.
- ViewModels are tested on the JVM: `@get:Rule InstantTaskExecutorRule`, `Dispatchers.setMain(
  StandardTestDispatcher())`, hand-written fakes, observe LiveData directly (note: `map`-derived
  LiveData like `paymentUiState` needs an active observer to emit in tests).
- **PosApiService fakes (the "new method breaks fakes" surface — adding a `PosApiService` method
  forces edits here)**:
  - `test/…/NoopPosApiService.kt` — `open class NoopPosApiService : PosApiService`, every method
    `= error("unused")` (subclasses override only what they use). **Adding any endpoint requires a
    new override here.**
  - `test/…/OfflineTestFakes.kt` — `FakeOfflineDb : OfflineSaleDao(), OfflineSaleItemDao`
    (in-memory, mirrors the unique-clientReference constraint), plus `sampleSale()/replaySale()`
    helpers and a fake `PosApiService`.
  - Other named fakes appear inline per test file. ~57 test files, e.g. `LoginViewModel`-adjacent
    `DeviceRegistrationFlowTest`, `DeviceActivationRequestTest`, `CashierCheckoutFallbackTest`,
    `OfflineSaleRepositoryTest`, `PaymentSyncRecoveryViewModelTest`, `ReceiptProjectorTest`,
    `TransactionHistoryReconcilerTest`, `PrinterCoordinatorTest`, `CashierContextPresenterTest`.
- Layout/a11y/font-scale gate tests are pure-JVM XML assertions: `FontScaleLayoutTest`,
  `AccessibilityLayoutTest`, `ReceiptHistoryLayoutTest`, `DesignSystemResourceTest`, `ResPaths.kt`.

## 10. Build variants, endpoints, manifest security

- Variants (`app/build.gradle.kts`): `debug` → `http://10.0.2.2:8000/` (emulator only);
  `pilot` (`initWith(debug)`, debuggable, debug-signed, installable) → `https://aishpos.online/`;
  `release` → `https://aishpos.online/`. URL via `BuildConfig.API_BASE_URL`
  (`core/config/AppConfig.DEFAULT_API_BASE_URL`).
- `network_security_config`: `src/main` (used by pilot/release) = **cleartext DENIED**, system
  trust only, no trust-all/hostname override. `src/debug` overlay permits cleartext ONLY for
  `10.0.2.2`/`localhost`/`127.0.0.1`. **Must not weaken.**
- Manifest (`app/src/main/AndroidManifest.xml`): permissions `INTERNET`, `ACCESS_NETWORK_STATE`,
  `BLUETOOTH`+`BLUETOOTH_ADMIN` (`maxSdkVersion=30`), `BLUETOOTH_CONNECT`. **No `BLUETOOTH_SCAN`,
  no location** (rule 58 — must stay). `allowBackup=false`. Only `MainActivity` is `exported=true`.

---

## Change surface (files likely edited/added by UIX-8C-07)

- **New**: a premium Auth/activation/session-recovery layer — e.g. `feature/settings/SettingsActivity`
  + ViewModel; possibly `feature/auth/DeviceActivationActivity/ViewModel`; a `SessionRecovery`/
  `AppStartRouter` state machine; `feature/settings/*` layouts + strings/tokens.
- **Edit**: `MainActivity` (richer start-destination decision / session-recovery routing),
  `LoginActivity`/`LoginViewModel` (premium visuals, re-auth), `AuthRepository` (logout that
  re-scopes local data + a guarded/forced-logout path; add 401 handling), `SessionManager`/
  `TokenStore` (likely migrate to EncryptedSharedPreferences — bumps `PosDatabase`? no; but touches
  token contract), `ApiClient`/`AuthInterceptor` (add a 401 `Authenticator`/session-invalidation
  seam), `CashierActivity` (add logout/settings entry + pending-unsync guard), `ServiceLocator`
  (wire new components), `AndroidRuntimeState`/`DeviceRepository` (wire the Sprint-34 activation +
  runtime-policy path that is currently dormant), test fakes `NoopPosApiService`/`OfflineTestFakes`
  if any `PosApiService` method is added, and a new `scripts/uix8c_*_gate.sh` + rule IDs
  (UIX8C-R211+) per the sprint pattern.

## Regression surface (must NOT break)

- **UIX-8C-06 receipt/history/printer**: `ReceiptProjection/Projector`, `ReceiptIdentity.matches`,
  `TransactionHistoryReconciler` one-row-per-logical-tx, `PrinterCoordinator` non-financial /
  single-flight, no `BLUETOOTH_SCAN`, server-decimal parse via `substringBefore('.')` (NOT
  `RupiahMoney.parse` — the ×100 grouping bug).
- **UIX-8C-04/05 offline durability**: stable `clientReference` reuse
  (`CashierViewModel.checkoutReference`, `OfflineSaleRepository.createOfflineCashSale(clientReference)`),
  `OfflineSyncStatus` PENDING/SYNCING/SYNCED/FAILED/CONFLICT semantics, `OFFLINE_PENDING`/`OfflineQueued`
  never shown as synced, `SYNCED` only on recorded server ack, `MAX_SYNC_ATTEMPTS=5` bounded retry,
  unique `clientReference` index + idempotent `createSale`, `TransportFailureClassifier`
  (HTTP/TLS never offline), cart-clear-only-after-durable-save, ViewModel double-submit guard.
  **A logout/account-switch that deletes the Room DB with `countPending()>0` would silently lose
  unsynced sales — an automatic NO-GO; must guard on `OfflineSaleRepository.pendingCount()`/`failedCount()`.**
- **Transport/manifest**: pilot/release cleartext-denied + TLS-only, `allowBackup=false`,
  only `MainActivity` exported, no HTTP logging on `pilot`, no secrets in `BuildConfig`.
- **Governance**: do not create a UIX-7/UIX-8 GO tag; UIX-7 `NO-GO — GO DEFERRED`, UIX-8
  `IMPLEMENTATION COMPLETE — GO DEFERRED`; historical `run-97fbb64-2af94aa` (R11/R18 FAIL) immutable.

---

## 15 most important files

1. `app/src/main/java/com/aishtech/poslite/MainActivity.kt` — cold-start router; start-destination decision (token-only, no state machine).
2. `…/core/ServiceLocator.kt` — manual DI singleton wiring every repo/store (extension point).
3. `…/core/session/SessionManager.kt` + `core/session/TokenStore.kt` — Sanctum token store (plain SharedPreferences; secure-storage TODO).
4. `…/core/network/AuthInterceptor.kt` + `core/network/ApiClient.kt` — Bearer attach; **no 401 handling** (session-recovery seam).
5. `…/data/repository/AuthRepository.kt` — login/`me`/logout; logout clears token only (account-switch gap).
6. `…/feature/auth/LoginViewModel.kt` + `feature/auth/LoginActivity.kt` — login + subscription/device bootstrap + one-shot nav.
7. `…/data/repository/DeviceRepository.kt` — Sprint-10 device register/heartbeat (wired) + `RegisteredDeviceResult`.
8. `…/core/runtime/DeviceActivationRequestFactory.kt` + `data/remote/dto/AndroidRuntimeDtos.kt` — Sprint-34 activation-code DTOs/factory (present, dormant).
9. `…/core/runtime/AndroidRuntimeState.kt` — server runtime posture model (present, not yet fetched/enforced).
10. `…/core/device/DeviceIdentityStore.kt` — opaque device UUID store (`aish_pos_device`), `getOrCreate/current/clear`.
11. `…/feature/cashier/CashierContext.kt` — `CashierContext` + pure `CashierContextPresenter.present(me,deviceName,reachable)`.
12. `…/core/database/PosDatabase.kt` + `data/local/dao/OfflineSaleDao.kt` — Room DB v2; `countPending()/countFailed()/findByClientReference()` for pending-unsync guards.
13. `…/data/repository/OfflineSaleRepository.kt` — durable offline queue; `pendingCount()/failedCount()`, `MAX_SYNC_ATTEMPTS=5`, `clientReference` idempotency.
14. `…/feature/printer/PrinterSettingsStore.kt` + `PrinterCoordinator.kt` — printer settings store (Settings-screen target) + non-financial print guard.
15. `app/build.gradle.kts` + `app/src/main/AndroidManifest.xml` + `src/{main,debug}/res/xml/network_security_config.xml` + `test/…/NoopPosApiService.kt` — variants/endpoints, permissions/allowBackup/exported, TLS policy, and the PosApiService fake to update on any new endpoint.
