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
        private val throwOnCreate: Throwable? = null,
    ) : PosApiService {
        var captured: CreateSaleRequestDto? = null

        override suspend fun login(request: LoginRequest): Response<LoginResponse> = error("unused")
        override suspend fun me(): Response<MeResponse> = error("unused")
        override suspend fun logout(): Response<Unit> = error("unused")
        override suspend fun syncProducts(updatedSince: String?, storeId: Long?): Response<ProductSyncResponse> = error("unused")
        override suspend fun syncCategories(updatedSince: String?, storeId: Long?): Response<CategorySyncResponse> = error("unused")
        override suspend fun getSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun cancelSale(id: Long): Response<SaleResponse> = error("unused")
        override suspend fun createQrisPayment(
            saleId: Long,
            request: com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto,
        ): Response<com.aishtech.poslite.data.remote.dto.QrisPaymentResponse> = error("unused")
        override suspend fun getPaymentStatus(paymentId: Long): Response<com.aishtech.poslite.data.remote.dto.QrisPaymentResponse> = error("unused")
        override suspend fun getReceipt(saleId: Long): Response<com.aishtech.poslite.data.remote.dto.ReceiptResponseDto> = error("unused")
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
        override suspend fun deviceStatus(): Response<com.aishtech.poslite.data.remote.dto.DeviceStatusResponseDto> = error("unused")
        override suspend fun submitSyncBatch(request: com.aishtech.poslite.data.remote.dto.SyncBatchRequestDto): Response<com.aishtech.poslite.data.remote.dto.SyncBatchResponseDto> = error("unused")

        override suspend fun createSale(request: CreateSaleRequestDto): Response<SaleResponse> {
            captured = request
            throwOnCreate?.let { throw it }
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

        val result = repo.checkoutCash(cart, paidAmount = 25000L)

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
            paidAmount = 25000L,
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
            paidAmount = 10000L,
        )

        assertTrue(result is ResultState.Error)
    }

    @Test
    fun `empty cart is rejected before any network call`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        val result = repo.checkoutCash(emptyList(), paidAmount = 0L)

        assertTrue(result is ResultState.Error)
        assertEquals(null, api.captured)
    }

    // UIX7-R054/R055 — an online checkout given an idempotency key must put it on
    // the wire as client_reference (with the ANDROID_ONLINE source) so the backend
    // dedupes a retry after a lost response instead of creating a second sale.
    @Test
    fun `checkout sends the client_reference and online source when given one`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        repo.checkoutCash(
            listOf(CartItem(1L, "Kopi", 10000.0, 2)),
            paidAmount = 25000L,
            clientReference = "online-ref-abc-123",
        )

        val request = api.captured!!
        assertEquals("online-ref-abc-123", request.clientReference)
        assertEquals("ANDROID_ONLINE", request.source)
    }

    // Backward compatibility: with no key, the request stays reference-less (the
    // pre-fix online behaviour) so unrelated callers are unaffected.
    @Test
    fun `checkout without a reference sends a null reference`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val repo = SalesRepository(api)

        repo.checkoutCash(listOf(CartItem(1L, "Kopi", 10000.0, 1)), paidAmount = 10000L)

        val request = api.captured!!
        assertEquals(null, request.clientReference)
        assertEquals(null, request.source)
    }

    // --- UIX-8C-04 submitCash / CheckoutOutcome (governed-fallback classification).

    private val cart = listOf(CartItem(1L, "Kopi", 10000.0, 1))

    @Test
    fun `submitCash success carries the sale and sends the online reference`() = runTest {
        val api = FakeApi(Response.success(SaleResponse(data = sampleSale())))
        val outcome = SalesRepository(api).submitCash(cart, 10000L, "ref-ok")

        assertTrue(outcome is SalesRepository.CheckoutOutcome.Success)
        assertEquals("ref-ok", api.captured!!.clientReference)
        assertEquals("ANDROID_ONLINE", api.captured!!.source)
    }

    // UIX8C-R099..R102 — a REACHABLE server that returns a canonical rejection is
    // NEVER eligible for offline fallback; it maps to Rejected, not TransportUnavailable.
    @Test
    fun `submitCash maps an http rejection to Rejected (never offline)`() = runTest {
        val error = Response.error<SaleResponse>(422, "{}".toResponseBody("application/json".toMediaType()))
        val outcome = SalesRepository(FakeApi(error)).submitCash(cart, 10000L, "ref-422")

        assertTrue(outcome is SalesRepository.CheckoutOutcome.Rejected)
        assertEquals(422, (outcome as SalesRepository.CheckoutOutcome.Rejected).code)
    }

    @Test
    fun `submitCash maps a 403 to Rejected (never offline)`() = runTest {
        val error = Response.error<SaleResponse>(403, "{}".toResponseBody("application/json".toMediaType()))
        val outcome = SalesRepository(FakeApi(error)).submitCash(cart, 10000L, "ref-403")
        assertTrue(outcome is SalesRepository.CheckoutOutcome.Rejected)
    }

    // UIX8C-R098 — a governed transport failure maps to TransportUnavailable (eligible).
    @Test
    fun `submitCash maps a transport failure to TransportUnavailable`() = runTest {
        val api = FakeApi(
            Response.success(SaleResponse(data = sampleSale())),
            throwOnCreate = java.net.UnknownHostException("aishpos.online"),
        )
        val outcome = SalesRepository(api).submitCash(cart, 10000L, "ref-dns")
        assertTrue(outcome is SalesRepository.CheckoutOutcome.TransportUnavailable)
    }

    // UIX8C-R103 — a TLS/security failure is NEVER eligible for offline fallback.
    @Test
    fun `submitCash maps a tls failure to Failed (never offline)`() = runTest {
        val api = FakeApi(
            Response.success(SaleResponse(data = sampleSale())),
            throwOnCreate = javax.net.ssl.SSLHandshakeException("bad cert"),
        )
        val outcome = SalesRepository(api).submitCash(cart, 10000L, "ref-tls")
        assertTrue(outcome is SalesRepository.CheckoutOutcome.Failed)
    }
}
