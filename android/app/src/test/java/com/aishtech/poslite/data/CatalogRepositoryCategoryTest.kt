package com.aishtech.poslite.data

import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.repository.CatalogRepository
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * UIX-8C-03 — [CatalogRepository.search] routes the (query, categoryId) pair to
 * the correct Room query (UIX8C-R065/R074/R075). Verified against recording fake
 * DAOs so the branch selection is provable without an on-device database.
 */
class CatalogRepositoryCategoryTest {

    private fun product(id: Long, categoryId: Long?) = LocalProductEntity(
        id = id, categoryId = categoryId, sku = null, barcode = null, name = "P$id",
        unit = null, sellingPrice = 0.0, effectiveSellingPrice = 0.0, isStockTracked = false,
        isActive = true, updatedAt = null, lastSyncedAt = 0,
    )

    private class RecordingProductDao : ProductDao {
        val calls = mutableListOf<String>()
        override suspend fun upsertAll(products: List<LocalProductEntity>) = Unit
        override suspend fun searchActiveProducts(query: String, limit: Int): List<LocalProductEntity> {
            calls += "search:$query"; return emptyList()
        }
        override suspend fun getActiveProducts(limit: Int): List<LocalProductEntity> {
            calls += "all"; return emptyList()
        }
        override suspend fun getActiveProductsByCategory(categoryId: Long, limit: Int): List<LocalProductEntity> {
            calls += "cat:$categoryId"; return emptyList()
        }
        override suspend fun searchActiveProductsByCategory(query: String, categoryId: Long, limit: Int): List<LocalProductEntity> {
            calls += "catSearch:$categoryId:$query"; return emptyList()
        }
        override suspend fun findById(id: Long): LocalProductEntity? = null
        override suspend fun countActive(): Int = 0
    }

    private class FakeCategoryDao(private val categories: List<LocalProductCategoryEntity>) : ProductCategoryDao {
        override suspend fun upsertAll(categories: List<LocalProductCategoryEntity>) = Unit
        override suspend fun getActiveCategories(): List<LocalProductCategoryEntity> = categories
        override suspend fun countActive(): Int = categories.size
    }

    private fun repo(dao: ProductDao) = CatalogRepository(dao, FakeCategoryDao(emptyList()))

    @Test
    fun blankQueryNoCategoryLoadsWholeCatalog() = runTest {
        val dao = RecordingProductDao()
        repo(dao).search("", null)
        assertEquals(listOf("all"), dao.calls)
    }

    @Test
    fun queryNoCategoryRunsSearch() = runTest {
        val dao = RecordingProductDao()
        repo(dao).search("kopi", null)
        assertEquals(listOf("search:kopi"), dao.calls)
    }

    @Test
    fun blankQueryWithCategoryLoadsCategory() = runTest {
        val dao = RecordingProductDao()
        repo(dao).search("", 7)
        assertEquals(listOf("cat:7"), dao.calls)
    }

    @Test
    fun queryWithCategoryRunsScopedSearch() = runTest {
        val dao = RecordingProductDao()
        repo(dao).search("  kopi  ", 7)
        assertEquals(listOf("catSearch:7:kopi"), dao.calls)
    }

    @Test
    fun legacySingleArgSearchIsAllCategoryScope() = runTest {
        val dao = RecordingProductDao()
        repo(dao).search("kopi")
        assertEquals(listOf("search:kopi"), dao.calls)
    }

    @Test
    fun categoriesReturnsActiveCategories() = runTest {
        val cats = listOf(
            LocalProductCategoryEntity(1, "Minuman", 0, true, null, 0),
            LocalProductCategoryEntity(2, "Makanan", 1, true, null, 0),
        )
        val repository = CatalogRepository(RecordingProductDao(), FakeCategoryDao(cats))
        assertEquals(cats, repository.categories())
    }
}
