package com.aishtech.poslite.core

import android.content.Context
import com.aishtech.poslite.core.database.PosDatabase
import com.aishtech.poslite.core.network.AndroidNetworkMonitor
import com.aishtech.poslite.core.network.ApiClient
import com.aishtech.poslite.core.network.NetworkMonitor
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.SharedPrefsTokenStore
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.QrisRepository
import com.aishtech.poslite.data.repository.ReceiptRepository
import com.aishtech.poslite.data.repository.SalesRepository
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

    fun session(context: Context): SessionManager =
        SessionManager(SharedPrefsTokenStore(context))

    fun api(context: Context): PosApiService =
        api ?: synchronized(this) {
            api ?: ApiClient.create(SharedPrefsTokenStore(context)).also { api = it }
        }

    fun authRepository(context: Context): AuthRepository =
        AuthRepository(api(context), session(context))

    fun catalogRepository(context: Context): CatalogRepository {
        val db = PosDatabase.getInstance(context)
        return CatalogRepository(db.productDao(), db.productCategoryDao())
    }

    fun salesRepository(context: Context): SalesRepository =
        SalesRepository(api(context))

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
