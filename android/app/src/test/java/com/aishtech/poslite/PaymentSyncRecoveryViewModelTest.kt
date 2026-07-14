package com.aishtech.poslite

import androidx.arch.core.executor.testing.InstantTaskExecutorRule
import com.aishtech.poslite.data.local.dao.AppSettingDao
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.data.repository.StockRepository
import com.aishtech.poslite.feature.cashier.CashierViewModel
import com.aishtech.poslite.feature.cashier.PaymentUiState
import com.aishtech.poslite.feature.sync.CatalogSyncManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import java.net.UnknownHostException

/**
 * UIX-8C-05 — the ViewModel-level payment/sync-recovery surface:
 *  - `paymentUiState` is a truthful projection of the canonical checkout state
 *    (a durable offline save projects to OfflineQueued, never Synced);
 *  - `requestManualRetry()` reuses the canonical sync path — no new checkout,
 *    reference, or offline row is created;
 *  - `onConnectivityRestored()` emits a one-shot reconnect signal and refreshes
 *    counts WITHOUT creating any transaction.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class PaymentSyncRecoveryViewModelTest {

    @get:Rule
    val instant = InstantTaskExecutorRule()

    private val dispatcher = StandardTestDispatcher()

    @Before fun setUp() = Dispatchers.setMain(dispatcher)
    @After fun tearDown() = Dispatchers.resetMain()

    private class StubProductDao : ProductDao {
        override suspend fun upsertAll(products: List<LocalProductEntity>) = Unit
        override suspend fun searchActiveProducts(query: String, limit: Int) = emptyList<LocalProductEntity>()
        override suspend fun getActiveProducts(limit: Int) = emptyList<LocalProductEntity>()
        override suspend fun getActiveProductsByCategory(categoryId: Long, limit: Int) = emptyList<LocalProductEntity>()
        override suspend fun searchActiveProductsByCategory(query: String, categoryId: Long, limit: Int) = emptyList<LocalProductEntity>()
        override suspend fun findById(id: Long): LocalProductEntity? = null
        override suspend fun countActive(): Int = 0
    }
    private class StubCategoryDao : ProductCategoryDao {
        override suspend fun upsertAll(categories: List<LocalProductCategoryEntity>) = Unit
        override suspend fun getActiveCategories() = emptyList<LocalProductCategoryEntity>()
        override suspend fun countActive(): Int = 0
    }
    private class StubSettingDao : AppSettingDao {
        override suspend fun getValue(key: String): String? = null
        override suspend fun put(setting: com.aishtech.poslite.data.local.entity.AppSettingEntity) = Unit
    }

    private class Fixture {
        val api = FakeSyncApi(emptyList()).apply { throwOnCreate = UnknownHostException("dns") }
        val db = FakeOfflineDb()
        val cart = CartRepository()
        val offline = OfflineSaleRepository(db, db, api, referenceProvider = { "vm-ref" }, clock = { 1L })
        val vm = CashierViewModel(
            catalogRepository = CatalogRepository(StubProductDao(), StubCategoryDao()),
            syncManager = CatalogSyncManager(api, StubProductDao(), StubCategoryDao(), StubSettingDao()),
            sales = SalesRepository(api),
            cart = cart,
            offline = offline,
            stock = StockRepository(api),
            referenceProvider = { "vm-checkout-ref" },
        )
    }

    @Test
    fun `paymentUiState projects a durable offline save as OfflineQueued not Synced`() = runTest(dispatcher) {
        val f = Fixture()
        f.cart.addProduct(1L, "Kopi", 10_000.0)
        // map()-derived LiveData only computes while observed.
        var ui: PaymentUiState? = null
        val observer = androidx.lifecycle.Observer<PaymentUiState> { ui = it }
        f.vm.paymentUiState.observeForever(observer)

        f.vm.checkoutCash(paidAmount = 10_000L)
        advanceUntilIdle()

        f.vm.paymentUiState.removeObserver(observer)
        assertTrue("expected OfflineQueued, got $ui", ui is PaymentUiState.OfflineQueued)
        assertTrue(ui !is PaymentUiState.Synced)
    }

    @Test
    fun `manual retry reuses the canonical queue without a new checkout or row`() = runTest(dispatcher) {
        val f = Fixture()
        f.cart.addProduct(1L, "Kopi", 10_000.0)
        f.vm.checkoutCash(paidAmount = 10_000L)
        advanceUntilIdle()

        assertEquals(1, f.db.sales.size)
        val refBefore = f.db.sales.values.single().clientReference

        f.vm.requestManualRetry()
        advanceUntilIdle()

        // Still exactly one logical transaction, same stable reference — the retry
        // went through the canonical sync path, not a new checkout.
        assertEquals(1, f.db.sales.size)
        assertEquals(refBefore, f.db.sales.values.single().clientReference)
    }

    @Test
    fun `reconnect emits a one-shot signal and creates no transaction`() = runTest(dispatcher) {
        val f = Fixture()
        assertEquals(0, f.db.sales.size)

        f.vm.onConnectivityRestored()
        advanceUntilIdle()

        // One-shot: consumed once, then null on a second read (survives rotation).
        assertNotNull(f.vm.reconnected.value?.getContentIfNotHandled())
        assertNull(f.vm.reconnected.value?.getContentIfNotHandled())
        // No transaction was created by the reconnect signal.
        assertEquals(0, f.db.sales.size)
    }
}
