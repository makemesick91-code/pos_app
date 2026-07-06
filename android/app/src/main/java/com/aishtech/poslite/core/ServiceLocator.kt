package com.aishtech.poslite.core

import android.content.Context
import com.aishtech.poslite.core.database.PosDatabase
import com.aishtech.poslite.core.network.ApiClient
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.session.SharedPrefsTokenStore
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.CatalogRepository
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
