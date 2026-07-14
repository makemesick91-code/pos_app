package com.aishtech.poslite

import androidx.arch.core.executor.testing.InstantTaskExecutorRule
import com.aishtech.poslite.data.local.dao.AppSettingDao
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.data.repository.StockRepository
import com.aishtech.poslite.feature.cashier.CashierViewModel
import com.aishtech.poslite.feature.cashier.CashierViewModel.CheckoutState
import com.aishtech.poslite.feature.sync.CatalogSyncManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import retrofit2.Response
import java.net.UnknownHostException
import javax.net.ssl.SSLHandshakeException

/**
 * UIX-8C-04 — the governed online→offline CASH fallback at the ViewModel level.
 *
 * This is the R11 regression: an operator submitting a CASH sale while the backend
 * is unreachable must end with a DURABLE offline transaction and a cleared cart —
 * never a bare error with `offline_sales` count 0. It also proves the inverse: a
 * canonical rejection or a security error must NEVER become an offline success.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class CashierCheckoutFallbackTest {

    @get:Rule
    val instant = InstantTaskExecutorRule()

    private val dispatcher = StandardTestDispatcher()

    @Before fun setUp() = Dispatchers.setMain(dispatcher)
    @After fun tearDown() = Dispatchers.resetMain()

    // --- Minimal stub DAOs (never touched by the checkout path under test).
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

    private class Fixture(throwOnCreate: Throwable?) {
        val api = FakeSyncApi(listOf(Response.success(SaleResponse(data = sampleSale()))))
            .apply { this.throwOnCreate = throwOnCreate }
        val db = FakeOfflineDb()
        val cart = CartRepository()
        val offline = OfflineSaleRepository(db, db, api, referenceProvider = { "vm-fallback-ref" }, clock = { 1_000L })
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

    // UIX8C-R098/R105/R107 — governed transport failure → durable offline save,
    // cart cleared only after the durable commit.
    @Test
    fun `transport failure falls back to a durable offline save and clears cart`() = runTest(dispatcher) {
        val f = Fixture(throwOnCreate = UnknownHostException("aishpos.online"))
        f.cart.addProduct(1L, "Kopi", 10000.0)

        f.vm.checkoutCash(paidAmount = 10000L)
        advanceUntilIdle()

        val state = f.vm.checkout.value
        assertTrue("expected OfflineSaved, got $state", state is CheckoutState.OfflineSaved)
        assertEquals(1, f.offline.pendingCount())        // one durable PENDING row
        assertTrue(f.cart.isEmpty())                     // cart cleared after durable save
    }

    // UIX8C-R097 — the SAME stable reference is used on the online attempt AND the
    // persisted offline row (so sync/backend dedupe on one key).
    @Test
    fun `online attempt and offline row share one stable reference`() = runTest(dispatcher) {
        val f = Fixture(throwOnCreate = UnknownHostException("dns"))
        f.cart.addProduct(1L, "Kopi", 10000.0)

        f.vm.checkoutCash(paidAmount = 10000L)
        advanceUntilIdle()

        val onlineRef = f.api.capturedRequests.single().clientReference
        val offlineRef = f.db.sales.values.single().clientReference
        assertEquals("vm-checkout-ref", onlineRef)
        assertEquals(onlineRef, offlineRef)
    }

    // UIX8C-R099..R102 — a canonical rejection (server reachable) must NEVER become
    // an offline success; the cart is preserved.
    @Test
    fun `canonical rejection keeps the cart and never queues offline`() = runTest(dispatcher) {
        val rejecting = FakeSyncApi(
            listOf(
                Response.error(
                    422,
                    "{}".toResponseBody("application/json".toMediaType()),
                ),
            ),
        )
        val db = FakeOfflineDb()
        val cart = CartRepository().apply { addProduct(1L, "Kopi", 10000.0) }
        val vm = CashierViewModel(
            catalogRepository = CatalogRepository(StubProductDao(), StubCategoryDao()),
            syncManager = CatalogSyncManager(rejecting, StubProductDao(), StubCategoryDao(), StubSettingDao()),
            sales = SalesRepository(rejecting),
            cart = cart,
            offline = OfflineSaleRepository(db, db, rejecting, referenceProvider = { "r" }, clock = { 1L }),
            stock = StockRepository(rejecting),
            referenceProvider = { "reject-ref" },
        )

        vm.checkoutCash(paidAmount = 10000L)
        advanceUntilIdle()

        assertTrue(vm.checkout.value is CheckoutState.Error)
        assertEquals(0, db.sales.size)                   // NEVER queued offline
        assertTrue(!cart.isEmpty())                      // cart preserved
    }

    // UIX8C-R103 — a TLS/security failure is NEVER offline; cart preserved.
    @Test
    fun `tls failure keeps the cart and never queues offline`() = runTest(dispatcher) {
        val f = Fixture(throwOnCreate = SSLHandshakeException("bad cert"))
        f.cart.addProduct(1L, "Kopi", 10000.0)

        f.vm.checkoutCash(paidAmount = 10000L)
        advanceUntilIdle()

        assertTrue(f.vm.checkout.value is CheckoutState.Error)
        assertEquals(0, f.db.sales.size)
        assertTrue(!f.cart.isEmpty())
    }

    // UIX8C-R109 — a rapid double tap while a submit is in flight creates at most
    // one durable offline transaction (the ViewModel re-entry guard).
    @Test
    fun `rapid double tap creates at most one offline transaction`() = runTest(dispatcher) {
        val f = Fixture(throwOnCreate = UnknownHostException("dns"))
        f.cart.addProduct(1L, "Kopi", 10000.0)

        f.vm.checkoutCash(paidAmount = 10000L)   // sets Submitting synchronously, launches
        f.vm.checkoutCash(paidAmount = 10000L)   // re-entry: guarded out
        advanceUntilIdle()

        assertEquals(1, f.db.sales.size)
        assertEquals(1, f.api.capturedRequests.size)
    }

    // UIX8C-R112/R113 — the durable row survives independently of ViewModel memory:
    // a fresh repository over the SAME local store still sees the pending row and
    // its reference (i.e. it survives process recreation).
    @Test
    fun `durable offline row survives process recreation`() = runTest(dispatcher) {
        val f = Fixture(throwOnCreate = UnknownHostException("dns"))
        f.cart.addProduct(1L, "Kopi", 10000.0)
        f.vm.checkoutCash(paidAmount = 10000L)
        advanceUntilIdle()

        // Simulate a process restart: a brand-new repository over the same store.
        val reborn = OfflineSaleRepository(f.db, f.db, f.api, clock = { 2L })
        assertEquals(1, reborn.pendingCount())
        assertEquals("vm-checkout-ref", reborn.recentSales().single().clientReference)
    }
}
