package com.aishtech.poslite

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.CurrentStockItemDto
import com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.ProductStockResponseDto
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.StockRepository
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Sprint 8 — the StockRepository fetches backend stock for display only. It has
 * no local authoritative stock state, so these tests assert it purely reflects
 * the backend response (success/error) through the API abstraction.
 */
class StockRepositoryTest {

    private class FakeApi(
        private val currentStock: Response<CurrentStockResponseDto>? = null,
    ) : PosApiService {
        var called = false

        override suspend fun login(request: LoginRequest): Response<LoginResponse> = error("unused")
        override suspend fun me(): Response<MeResponse> = error("unused")
        override suspend fun logout(): Response<Unit> = error("unused")
        override suspend fun syncProducts(updatedSince: String?, storeId: Long?): Response<ProductSyncResponse> = error("unused")
        override suspend fun syncCategories(updatedSince: String?, storeId: Long?): Response<CategorySyncResponse> = error("unused")
        override suspend fun createSale(request: CreateSaleRequestDto): Response<SaleResponse> = error("unused")
        override suspend fun getSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun cancelSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun createQrisPayment(saleId: Long, request: CreateQrisPaymentRequestDto): Response<QrisPaymentResponse> = error("unused")
        override suspend fun getPaymentStatus(paymentId: Long): Response<QrisPaymentResponse> = error("unused")
        override suspend fun getReceipt(saleId: Long): Response<ReceiptResponseDto> = error("unused")
        override suspend fun getProductStock(productId: Long): Response<ProductStockResponseDto> = error("unused")
        override suspend fun getDailySalesReport(storeId: Long?, date: String?, cashierId: Long?): Response<com.aishtech.poslite.data.remote.dto.DailySalesReportResponseDto> = error("unused")
        override suspend fun getPaymentSummary(storeId: Long?, date: String?): Response<com.aishtech.poslite.data.remote.dto.PaymentSummaryResponseDto> = error("unused")
        override suspend fun getInventoryMovementsSummary(storeId: Long?, date: String?): Response<com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryResponseDto> = error("unused")
        override suspend fun createDailyClosing(request: com.aishtech.poslite.data.remote.dto.CreateDailyClosingRequestDto): Response<com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto> = error("unused")
        override suspend fun getDailyClosings(storeId: Long?): Response<com.aishtech.poslite.data.remote.dto.DailyClosingListResponseDto> = error("unused")
        override suspend fun getDailyClosing(id: Long): Response<com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto> = error("unused")
        override suspend fun getSubscriptionStatus(): Response<com.aishtech.poslite.data.remote.dto.SubscriptionStatusResponseDto> = error("unused")
        override suspend fun registerDevice(request: com.aishtech.poslite.data.remote.dto.RegisterDeviceRequestDto): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")
        override suspend fun deviceHeartbeat(request: com.aishtech.poslite.data.remote.dto.DeviceHeartbeatRequestDto): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")
        override suspend fun listDevices(status: String?): Response<com.aishtech.poslite.data.remote.dto.DeviceListResponseDto> = error("unused")
        override suspend fun revokeDevice(deviceId: Long): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")

        override suspend fun getCurrentStock(storeId: Long?, query: String?, limit: Int?): Response<CurrentStockResponseDto> {
            called = true
            return currentStock!!
        }
    }

    @Test
    fun `getCurrentStock returns backend data through the api abstraction`() = runTest {
        val api = FakeApi(
            Response.success(
                CurrentStockResponseDto(
                    data = listOf(
                        CurrentStockItemDto(
                            productId = 1L,
                            sku = "SKU-1",
                            barcode = null,
                            name = "Kopi",
                            unit = "pcs",
                            isStockTracked = true,
                            currentStock = "12.00",
                        ),
                    ),
                ),
            ),
        )
        val repo = StockRepository(api)

        val result = repo.getCurrentStock()

        assertTrue(api.called)
        result as ResultState.Success
        assertEquals(1, result.data.size)
        assertEquals("12.00", result.data.first().currentStock)
    }

    @Test
    fun `backend error surfaces as Error result`() = runTest {
        val error = Response.error<CurrentStockResponseDto>(
            500,
            "{}".toResponseBody("application/json".toMediaType()),
        )
        val repo = StockRepository(FakeApi(error))

        val result = repo.getCurrentStock()

        assertTrue(result is ResultState.Error)
    }
}
