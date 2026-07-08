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
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.repository.ReceiptRepository
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.Response

/**
 * Pure-JVM tests for the Sprint 6 receipt repository. The repository only relays
 * the backend-approved receipt (including printable / block reason); it never
 * recomputes totals or decides eligibility itself.
 */
class ReceiptRepositoryTest {

    private class FakeApi(
        private val receipt: Response<ReceiptResponseDto>? = null,
    ) : PosApiService {
        var capturedSaleId: Long? = null

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
        override suspend fun getCurrentStock(storeId: Long?, query: String?, limit: Int?): Response<com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto> = error("unused")
        override suspend fun getProductStock(productId: Long): Response<com.aishtech.poslite.data.remote.dto.ProductStockResponseDto> = error("unused")
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
        override suspend fun activateDevice(request: com.aishtech.poslite.data.remote.dto.ActivateDeviceRequestDto): Response<com.aishtech.poslite.data.remote.dto.DeviceActivationResponseDto> = error("unused")
        override suspend fun androidDeviceHeartbeat(): Response<com.aishtech.poslite.data.remote.dto.DeviceActivationResponseDto> = error("unused")
        override suspend fun getAndroidRuntimePolicy(): Response<com.aishtech.poslite.data.remote.dto.AndroidRuntimePolicyResponseDto> = error("unused")
        override suspend fun submitSyncBatch(request: com.aishtech.poslite.data.remote.dto.SyncBatchRequestDto): Response<com.aishtech.poslite.data.remote.dto.SyncBatchResponseDto> = error("unused")

        override suspend fun getReceipt(saleId: Long): Response<ReceiptResponseDto> {
            capturedSaleId = saleId
            return receipt!!
        }
    }

    private fun receiptDto(printable: Boolean = true, status: String = "FINAL") = ReceiptDto(
        saleId = 1,
        invoiceNumber = "POS-A1-20260707-000001",
        receiptStatus = status,
        printable = printable,
        printBlockReason = if (printable) null else "Penjualan belum dibayar.",
        store = null,
        cashier = null,
        saleDate = null,
        paymentStatus = if (printable) "PAID" else "UNPAID",
        items = emptyList(),
        payments = emptyList(),
        totals = null,
        footer = "Terima kasih",
    )

    @Test
    fun `returns backend-approved printable receipt`() = runTest {
        val api = FakeApi(receipt = Response.success(ReceiptResponseDto(data = receiptDto())))
        val repo = ReceiptRepository(api)

        val result = repo.getReceipt(1L)

        assertTrue(result is ResultState.Success)
        assertEquals(1L, api.capturedSaleId)
        result as ResultState.Success
        assertTrue(result.data.printable)
        assertEquals("FINAL", result.data.receiptStatus)
    }

    @Test
    fun `relays a not-printable receipt without altering the flag`() = runTest {
        val api = FakeApi(
            receipt = Response.success(
                ReceiptResponseDto(data = receiptDto(printable = false, status = "NOT_PRINTABLE")),
            ),
        )
        val repo = ReceiptRepository(api)

        val result = repo.getReceipt(1L) as ResultState.Success

        assertEquals(false, result.data.printable)
        assertEquals("Penjualan belum dibayar.", result.data.printBlockReason)
    }

    @Test
    fun `404 surfaces as an error result`() = runTest {
        val error = Response.error<ReceiptResponseDto>(
            404,
            "{}".toResponseBody("application/json".toMediaType()),
        )
        val repo = ReceiptRepository(FakeApi(receipt = error))

        val result = repo.getReceipt(1L)

        assertTrue(result is ResultState.Error)
    }
}
