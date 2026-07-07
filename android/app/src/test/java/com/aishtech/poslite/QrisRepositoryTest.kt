package com.aishtech.poslite

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.QrisPaymentDto
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.QrisRepository
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Pure-JVM tests for the Sprint 5 QRIS repository. A fake PosApiService captures
 * the outgoing request so we can assert the app only sends a provider (never a
 * gateway credential) and correctly surfaces success/error.
 */
class QrisRepositoryTest {

    private class FakeApi(
        private val create: Response<QrisPaymentResponse>? = null,
        private val status: Response<QrisPaymentResponse>? = null,
    ) : PosApiService {
        var capturedProvider: String? = null
        var capturedSaleId: Long? = null

        override suspend fun login(request: LoginRequest): Response<LoginResponse> = error("unused")
        override suspend fun me(): Response<MeResponse> = error("unused")
        override suspend fun logout(): Response<Unit> = error("unused")
        override suspend fun syncProducts(updatedSince: String?, storeId: Long?): Response<ProductSyncResponse> = error("unused")
        override suspend fun syncCategories(updatedSince: String?, storeId: Long?): Response<CategorySyncResponse> = error("unused")
        override suspend fun createSale(request: CreateSaleRequestDto): Response<SaleResponse> = error("unused")
        override suspend fun getSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun cancelSale(id: Long): Response<SaleResponse> = error("unused")

        override suspend fun createQrisPayment(
            saleId: Long,
            request: CreateQrisPaymentRequestDto,
        ): Response<QrisPaymentResponse> {
            capturedSaleId = saleId
            capturedProvider = request.provider
            return create!!
        }

        override suspend fun getPaymentStatus(paymentId: Long): Response<QrisPaymentResponse> = status!!
        override suspend fun getReceipt(saleId: Long): Response<ReceiptResponseDto> = error("unused")
        override suspend fun getCurrentStock(storeId: Long?, query: String?, limit: Int?): Response<com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto> = error("unused")
        override suspend fun getProductStock(productId: Long): Response<com.aishtech.poslite.data.remote.dto.ProductStockResponseDto> = error("unused")
    }

    private fun samplePayment(status: String = "PENDING") = QrisPaymentDto(
        id = 10,
        saleId = 1,
        method = "QRIS",
        provider = "FAKE",
        status = status,
        amount = "20000.00",
        providerReference = "FAKE-QRIS-ABC",
        qrPayload = "FAKE-QRIS|SALE:POS-A1-20260707-000001|AMOUNT:20000.00|REF:FAKE-QRIS-ABC",
        qrImageUrl = null,
        paymentUrl = null,
        expiredAt = "2026-07-07T00:15:00Z",
        paidAt = null,
        salePaymentStatus = "PENDING",
    )

    @Test
    fun `create sends only the provider and returns pending payment`() = runTest {
        val api = FakeApi(create = Response.success(QrisPaymentResponse(data = samplePayment())))
        val repo = QrisRepository(api)

        val result = repo.createQrisPayment(saleId = 1L, provider = "fake")

        assertTrue(result is ResultState.Success)
        assertEquals(1L, api.capturedSaleId)
        assertEquals("fake", api.capturedProvider)
        result as ResultState.Success
        assertEquals("PENDING", result.data.status)
        assertEquals("FAKE-QRIS-ABC", result.data.providerReference)
    }

    @Test
    fun `status poll returns the latest payment`() = runTest {
        val api = FakeApi(status = Response.success(QrisPaymentResponse(data = samplePayment("PAID"))))
        val repo = QrisRepository(api)

        val result = repo.getPaymentStatus(10L)

        result as ResultState.Success
        assertEquals("PAID", result.data.status)
    }

    @Test
    fun `backend error surfaces as Error result`() = runTest {
        val error = Response.error<QrisPaymentResponse>(
            422,
            "{}".toResponseBody("application/json".toMediaType()),
        )
        val repo = QrisRepository(FakeApi(create = error))

        val result = repo.createQrisPayment(saleId = 1L, provider = "fake")

        assertTrue(result is ResultState.Error)
    }
}
