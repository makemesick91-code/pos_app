package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.config.AppConfig
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.LocalProductEntity

/**
 * Read access to the locally cached catalog. Search runs entirely against Room
 * with a bounded LIMIT — no network call and no full-table load.
 */
class CatalogRepository(
    private val productDao: ProductDao,
    private val categoryDao: ProductCategoryDao,
) {

    suspend fun search(query: String): List<LocalProductEntity> {
        val trimmed = query.trim()
        return if (trimmed.isEmpty()) {
            productDao.getActiveProducts(AppConfig.PRODUCT_LIST_LIMIT)
        } else {
            productDao.searchActiveProducts(trimmed, AppConfig.SEARCH_RESULT_LIMIT)
        }
    }

    suspend fun activeProductCount(): Int = productDao.countActive()

    suspend fun activeCategoryCount(): Int = categoryDao.countActive()
}
