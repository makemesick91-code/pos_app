package com.aishtech.poslite

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.SaleDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.feature.cashier.CartItem
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Pure-JVM tests for Sprint 4 cash checkout submission. A fake PosApiService
 * captures the outgoing request so we can assert the cart is converted to the
 * backend contract without forging tenant/total fields.
 */
class SalesRepositoryTest {

    private class FakeApi(
        private val result: Response<SaleResponse>,
    ) : PosApiService {
        var captured: CreateSaleRequestDto? = null

        override suspend fun login(request: LoginRequest): Response<LoginResponse> = error("unused")
        override suspend fun me(): Response<MeResponse> = error("unused")
        override suspend fun logout(): Response<Unit> = error("unused")
        override suspend fun syncProducts(updatedSince: String?, storeId: Long?): Response<ProductSyncResponse> = error("unused")
        override suspend fun syncCategories(updatedSince: String?, storeId: Long?): Response<CategorySyncResponse> = error("unused")
        override suspend fun getSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun cancelSale(id: Long): Response<SaleResponse> = error("unused")

        override suspend fun createSale(request: CreateSaleRequestDto): Response<SaleResponse> {
            captured = request
            return result
        }
    }

    private fun sampleSale() = SaleDto(
        id = 1,
        storeId = 1,
        invoiceNumber = "POS-A1-20260707-000001",
        saleDate = null,
        subtotal = "20000.00",
        discountTotal = "0.00",
        taxTotal = "0.00",
        grandTotal = "20000.00",
        paidTotal = "25000.00",
        changeTotal = "5000.00",
        paymentStatus = "PAID",
        syncStatus = "SYNCED",
        source = "ANDROID_ONLINE",
    )

    @Test
    fun `checkout converts cart to request and never sends totals`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        val cart = listOf(
            CartItem(productId = 1L, name = "Kopi", unitPrice = 10000.0, quantity = 2),
        )

        val result = repo.checkoutCash(cart, paidAmount = 25000.0)

        assertTrue(result is ResultState.Success)
        val request = api.captured!!
        assertEquals(1, request.items.size)
        assertEquals(1L, request.items.first().productId)
        assertEquals(2, request.items.first().qty)
        assertEquals("CASH", request.payment.method)
        assertEquals("25000.00", request.payment.paidAmount)
    }

    @Test
    fun `successful checkout returns the sale data`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        val result = repo.checkoutCash(
            listOf(CartItem(1L, "Kopi", 10000.0, 2)),
            paidAmount = 25000.0,
        )

        result as ResultState.Success
        assertEquals("POS-A1-20260707-000001", result.data.invoiceNumber)
        assertEquals("5000.00", result.data.changeTotal)
    }

    @Test
    fun `backend error surfaces as Error result`() = runTest {
        val error = Response.error<SaleResponse>(
            422,
            "{}".toResponseBody("application/json".toMediaType()),
        )
        val repo = SalesRepository(FakeApi(error))

        val result = repo.checkoutCash(
            listOf(CartItem(1L, "Kopi", 10000.0, 1)),
            paidAmount = 10000.0,
        )

        assertTrue(result is ResultState.Error)
    }

    @Test
    fun `empty cart is rejected before any network call`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        val result = repo.checkoutCash(emptyList(), paidAmount = 0.0)

        assertTrue(result is ResultState.Error)
        assertEquals(null, api.captured)
    }
}
