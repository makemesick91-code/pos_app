package com.aishtech.poslite.feature.sync

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.local.CatalogMappers
import com.aishtech.poslite.data.local.dao.AppSettingDao
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.AppSettingEntity

/**
 * Manual catalog sync (Sprint 3). Pulls categories then products from the
 * backend using the stored incremental `updated_since` cursor, upserts them
 * into Room, and advances the cursor only on success.
 *
 * A failed sync never clears the local cache. Sprint 3 does NOT push local
 * sales — offline sales sync is out of scope until a later sprint.
 */
class CatalogSyncManager(
    private val api: PosApiService,
    private val productDao: ProductDao,
    private val categoryDao: ProductCategoryDao,
    private val settingDao: AppSettingDao,
) {

    data class SyncSummary(val products: Int, val categories: Int)

    suspend fun sync(): ResultState<SyncSummary> {
        return try {
            val now = System.currentTimeMillis()

            // Categories first so products can resolve their category names.
            val categoriesSince = settingDao.getValue(AppSettingEntity.KEY_LAST_CATEGORIES_SYNC_AT)
            val categoryResponse = api.syncCategories(updatedSince = categoriesSince)
            if (!categoryResponse.isSuccessful) {
                return ResultState.Error("Sync kategori gagal (kode ${categoryResponse.code()}).")
            }
            val categories = categoryResponse.body()?.data.orEmpty()
            categoryDao.upsertAll(CatalogMappers.toCategoryEntities(categories, now))

            val productsSince = settingDao.getValue(AppSettingEntity.KEY_LAST_PRODUCTS_SYNC_AT)
            val productResponse = api.syncProducts(updatedSince = productsSince)
            if (!productResponse.isSuccessful) {
                return ResultState.Error("Sync produk gagal (kode ${productResponse.code()}).")
            }
            val products = productResponse.body()?.data.orEmpty()
            productDao.upsertAll(CatalogMappers.toProductEntities(products, now))

            // Advance incremental cursors only after both upserts succeeded.
            val cursor = isoTimestamp(now)
            settingDao.setValue(AppSettingEntity.KEY_LAST_CATEGORIES_SYNC_AT, cursor)
            settingDao.setValue(AppSettingEntity.KEY_LAST_PRODUCTS_SYNC_AT, cursor)

            ResultState.Success(SyncSummary(products = products.size, categories = categories.size))
        } catch (e: Exception) {
            // Local cache is preserved on any failure.
            ResultState.Error("Tidak dapat menyinkronkan katalog.")
        }
    }

    private fun isoTimestamp(epochMillis: Long): String {
        val format = java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", java.util.Locale.US)
        format.timeZone = java.util.TimeZone.getTimeZone("UTC")
        return format.format(java.util.Date(epochMillis))
    }
}
