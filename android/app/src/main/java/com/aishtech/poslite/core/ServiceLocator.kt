package com.aishtech.poslite.core

import android.content.Context
import com.aishtech.poslite.core.database.PosDatabase
import com.aishtech.poslite.core.device.DeviceIdentityStore
import com.aishtech.poslite.core.network.AndroidNetworkMonitor
import com.aishtech.poslite.core.network.ApiClient
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.runtime.ActivationStateStore
import com.aishtech.poslite.core.runtime.RuntimeContextStore
import com.aishtech.poslite.core.runtime.SharedPrefsActivationStateStore
import com.aishtech.poslite.core.session.LocalDataCleaner
import com.aishtech.poslite.core.session.LogoutGuard
import com.aishtech.poslite.core.session.ScopedStore
import com.aishtech.poslite.core.session.DataScope
import com.aishtech.poslite.core.session.SecureTokenStore
import com.aishtech.poslite.core.session.SessionEventBus
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.SharedPrefsSecureKeyValueStore
import com.aishtech.poslite.core.session.SharedPrefsTokenStore
import com.aishtech.poslite.core.session.UnsyncedCounter
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import com.aishtech.poslite.feature.settings.AppBuildInfo
import com.aishtech.poslite.feature.settings.SettingsViewModel
import com.aishtech.poslite.feature.startup.StartupViewModel
import com.aishtech.poslite.feature.activation.DeviceActivationViewModel
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.ClosingRepository
import com.aishtech.poslite.data.repository.DeviceRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.QrisRepository
import com.aishtech.poslite.data.repository.ReceiptRepository
import com.aishtech.poslite.data.repository.ReportRepository
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.data.repository.StockRepository
import com.aishtech.poslite.data.repository.SubscriptionRepository
import com.aishtech.poslite.feature.printer.BluetoothPrinterConnection
import com.aishtech.poslite.feature.printer.PrinterCoordinator
import com.aishtech.poslite.feature.printer.PrinterRepository
import com.aishtech.poslite.feature.printer.PrinterSettingsStore
import com.aishtech.poslite.feature.sync.CatalogSyncManager

/**
 * Minimal manual dependency wiring for Sprint 3 (no DI framework — keeps the
 * app lightweight). All singletons are derived from the application context.
 */
object ServiceLocator {

    @Volatile
    private var api: PosApiService? = null

    @Volatile
    private var deviceIdentity: DeviceIdentityStore? = null

    @Volatile
    private var secureTokenStore: SecureTokenStore? = null

    @Volatile
    private var sessionEventBus: SessionEventBus? = null

    // UIX-8C-07 — the Keystore-backed token store is a process singleton so the
    // session facade and the OkHttp AuthInterceptor read/write the same secured
    // token (UIX8C-R219).
    fun tokenStore(context: Context): SecureTokenStore =
        secureTokenStore ?: synchronized(this) {
            secureTokenStore ?: SecureTokenStore.create(context.applicationContext)
                .also { secureTokenStore = it }
        }

    fun sessionEventBus(): SessionEventBus =
        sessionEventBus ?: synchronized(this) {
            sessionEventBus ?: SessionEventBus().also { sessionEventBus = it }
        }

    fun session(context: Context): SessionManager =
        SessionManager(tokenStore(context))

    fun activationStateStore(context: Context): ActivationStateStore =
        SharedPrefsActivationStateStore(context.applicationContext)

    fun runtimeContextStore(context: Context): RuntimeContextStore =
        RuntimeContextStore(SharedPrefsSecureKeyValueStore(context.applicationContext, "aish_pos_runtime"))

    // Sprint 10 — single locally generated device identity for the install.
    fun deviceIdentityStore(context: Context): DeviceIdentityStore =
        deviceIdentity ?: synchronized(this) {
            deviceIdentity ?: DeviceIdentityStore.create(context.applicationContext)
                .also { deviceIdentity = it }
        }

    fun api(context: Context): PosApiService =
        api ?: synchronized(this) {
            api ?: ApiClient.create(
                tokenStore = tokenStore(context),
                deviceUuidProvider = deviceIdentityStore(context),
                sessionEventBus = sessionEventBus(),
            ).also { api = it }
        }

    fun subscriptionRepository(context: Context): SubscriptionRepository =
        SubscriptionRepository(api(context))

    fun deviceRepository(context: Context): DeviceRepository =
        DeviceRepository(api(context), deviceIdentityStore(context))

    fun authRepository(context: Context): AuthRepository =
        AuthRepository(api(context), session(context))

    fun catalogRepository(context: Context): CatalogRepository {
        val db = PosDatabase.getInstance(context)
        return CatalogRepository(db.productDao(), db.productCategoryDao())
    }

    fun salesRepository(context: Context): SalesRepository =
        SalesRepository(api(context))

    // Sprint 8 — read-only inventory stock for lightweight cashier visibility.
    fun stockRepository(context: Context): StockRepository =
        StockRepository(api(context))

    // Sprint 9 — read-only backend report summaries + daily closing action.
    fun reportRepository(context: Context): ReportRepository =
        ReportRepository(api(context))

    fun closingRepository(context: Context): ClosingRepository =
        ClosingRepository(api(context))

    // Sprint 7 — offline CASH sale queue + sync.
    fun offlineSaleRepository(context: Context): OfflineSaleRepository {
        val db = PosDatabase.getInstance(context)
        return OfflineSaleRepository(
            offlineSaleDao = db.offlineSaleDao(),
            offlineSaleItemDao = db.offlineSaleItemDao(),
            api = api(context),
        )
    }

    fun networkMonitor(context: Context): NetworkMonitor =
        AndroidNetworkMonitor(context.applicationContext)

    fun qrisRepository(context: Context): QrisRepository =
        QrisRepository(api(context))

    fun receiptRepository(context: Context): ReceiptRepository =
        ReceiptRepository(api(context))

    // Sprint 6 — receipt printing foundation. Printer settings live locally (no
    // payment credentials); the transport is Android-native Bluetooth SPP.
    fun printerRepository(context: Context): PrinterRepository =
        PrinterRepository(
            connection = BluetoothPrinterConnection(context),
            settingsStore = PrinterSettingsStore(context),
        )

    // UIX-8C-06 — the single canonical print entry point (concurrency-guarded,
    // non-financial). Receipt/reprint go through this, never straight to the
    // transport.
    fun printerCoordinator(context: Context): PrinterCoordinator =
        PrinterCoordinator(printerRepository(context))

    fun catalogSyncManager(context: Context): CatalogSyncManager {
        val db = PosDatabase.getInstance(context)
        return CatalogSyncManager(
            api = api(context),
            productDao = db.productDao(),
            categoryDao = db.productCategoryDao(),
            settingDao = db.appSettingDao(),
        )
    }

    // ---- UIX-8C-07: premium auth / device / settings / session recovery ----

    fun deviceActivationRepository(context: Context): DeviceActivationRepository {
        val identity = deviceIdentityStore(context)
        val secure = tokenStore(context)
        return DeviceActivationRepository(
            api = api(context),
            deviceUuidProvider = { identity.getOrCreateDeviceUuid() },
            installationIdProvider = { secure.getOrCreateInstallationId() },
            deviceLabelProvider = { android.os.Build.MODEL ?: "Perangkat Kasir" },
            appVersionName = com.aishtech.poslite.BuildConfig.VERSION_NAME,
        )
    }

    /** Adapts the durable offline queue to the unsynced-count contract used by the
     *  logout guard (UIX8C-R231). */
    fun unsyncedCounter(context: Context): UnsyncedCounter {
        val offline = offlineSaleRepository(context)
        return object : UnsyncedCounter {
            override suspend fun pendingCount(): Int = offline.pendingCount()
            override suspend fun failedCount(): Int = offline.failedCount()
        }
    }

    fun logoutGuard(context: Context): LogoutGuard = LogoutGuard(unsyncedCounter(context))

    /** The production cross-tenant cleanup registry. Device/global stores are never
     *  registered here so they survive logout/switch (UIX8C-R236). */
    fun localDataCleaner(context: Context): LocalDataCleaner {
        val app = context.applicationContext
        return LocalDataCleaner(
            listOf(
                ScopedStore("cashier_prefs", DataScope.CASHIER) {
                    app.getSharedPreferences("aish_pos_cashier", Context.MODE_PRIVATE)
                        .edit().clear().apply()
                },
                ScopedStore("catalog_cursor", DataScope.OUTLET) {
                    app.getSharedPreferences("aish_pos_catalog_cursor", Context.MODE_PRIVATE)
                        .edit().clear().apply()
                },
            ),
        )
    }

    private fun appBuildInfo(context: Context): AppBuildInfo {
        val installShort = tokenStore(context).getOrCreateInstallationId()
            .replace("-", "").take(8)
        return AppBuildInfo(
            versionName = com.aishtech.poslite.BuildConfig.VERSION_NAME,
            versionCode = com.aishtech.poslite.BuildConfig.VERSION_CODE.toLong(),
            buildType = com.aishtech.poslite.BuildConfig.BUILD_TYPE,
            packageName = context.packageName,
            androidRelease = "Android ${android.os.Build.VERSION.RELEASE}",
            deviceModel = "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}",
            installationIdShort = installShort,
        )
    }

    fun buildStartupViewModel(context: Context): StartupViewModel = StartupViewModel(
        session = session(context),
        authRepo = authRepository(context),
        deviceRepo = deviceActivationRepository(context),
        unsyncedCounter = unsyncedCounter(context),
        contextStore = runtimeContextStore(context),
        activationState = activationStateStore(context),
        networkMonitor = networkMonitor(context),
    )

    fun buildDeviceActivationViewModel(context: Context): DeviceActivationViewModel =
        DeviceActivationViewModel(
            repository = deviceActivationRepository(context),
            activationState = activationStateStore(context),
        )

    fun buildSettingsViewModel(context: Context): SettingsViewModel = SettingsViewModel(
        authRepo = authRepository(context),
        deviceRepo = deviceActivationRepository(context),
        logoutGuard = logoutGuard(context),
        cleaner = localDataCleaner(context),
        session = session(context),
        unsyncedCounter = unsyncedCounter(context),
        networkMonitor = networkMonitor(context),
        appBuildInfo = appBuildInfo(context),
    )
}
