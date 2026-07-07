package com.aishtech.poslite.core

import android.content.Context
import com.aishtech.poslite.core.database.PosDatabase
import com.aishtech.poslite.core.device.DeviceIdentityStore
import com.aishtech.poslite.core.network.AndroidNetworkMonitor
import com.aishtech.poslite.core.network.ApiClient
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.SharedPrefsTokenStore
import com.aishtech.poslite.data.repository.AuthRepository
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

    fun session(context: Context): SessionManager =
        SessionManager(SharedPrefsTokenStore(context))

    // Sprint 10 — single locally generated device identity for the install.
    fun deviceIdentityStore(context: Context): DeviceIdentityStore =
        deviceIdentity ?: synchronized(this) {
            deviceIdentity ?: DeviceIdentityStore.create(context.applicationContext)
                .also { deviceIdentity = it }
        }

    fun api(context: Context): PosApiService =
        api ?: synchronized(this) {
            api ?: ApiClient.create(
                tokenStore = SharedPrefsTokenStore(context),
                deviceUuidProvider = deviceIdentityStore(context),
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

    fun catalogSyncManager(context: Context): CatalogSyncManager {
        val db = PosDatabase.getInstance(context)
        return CatalogSyncManager(
            api = api(context),
            productDao = db.productDao(),
            categoryDao = db.productCategoryDao(),
            settingDao = db.appSettingDao(),
        )
    }
}
