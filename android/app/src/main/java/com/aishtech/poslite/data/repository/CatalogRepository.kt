package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.config.AppConfig
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity

/**
 * Read access to the locally cached catalog. Search runs entirely against Room
 * with a bounded LIMIT — no network call and no full-table load.
 *
 * UIX-8C-03 adds category-scoped reads: [categories] returns the active category
 * list for the filter control and [search] accepts an optional `categoryId`.
 * Category filtering re-queries products only and NEVER mutates the cart
 * (UIX8C-R074); clearing the filter (`categoryId = null`, blank query) restores
 * the canonical catalog (UIX8C-R075).
 */
class CatalogRepository(
    private val productDao: ProductDao,
    private val categoryDao: ProductCategoryDao,
) {

    /** Backwards-compatible search across the whole (tenant-scoped) catalog. */
    suspend fun search(query: String): List<LocalProductEntity> = search(query, null)

    /**
     * Search the local catalog optionally scoped to a single category. The four
     * branches keep the "all vs filtered" and "browse vs search" cases explicit
     * so an empty result can be attributed truthfully by the caller.
     */
    suspend fun search(query: String, categoryId: Long?): List<LocalProductEntity> {
        val trimmed = query.trim()
        return when {
            categoryId == null && trimmed.isEmpty() ->
                productDao.getActiveProducts(AppConfig.PRODUCT_LIST_LIMIT)
            categoryId == null ->
                productDao.searchActiveProducts(trimmed, AppConfig.SEARCH_RESULT_LIMIT)
            trimmed.isEmpty() ->
                productDao.getActiveProductsByCategory(categoryId, AppConfig.PRODUCT_LIST_LIMIT)
            else ->
                productDao.searchActiveProductsByCategory(trimmed, categoryId, AppConfig.SEARCH_RESULT_LIMIT)
        }
    }

    /** Active categories for the cashier filter control, canonical sort order. */
    suspend fun categories(): List<LocalProductCategoryEntity> = categoryDao.getActiveCategories()

    suspend fun activeProductCount(): Int = productDao.countActive()

    suspend fun activeCategoryCount(): Int = categoryDao.countActive()
}
