package com.aishtech.poslite

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateDailyClosingRequestDto
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto
import com.aishtech.poslite.data.remote.dto.DailyClosingListResponseDto
import com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto
import com.aishtech.poslite.data.remote.dto.DailySalesReportResponseDto
import com.aishtech.poslite.data.remote.dto.DeviceHeartbeatRequestDto
import com.aishtech.poslite.data.remote.dto.DeviceListResponseDto
import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryResponseDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.PaymentSummaryResponseDto
import com.aishtech.poslite.data.remote.dto.ProductStockResponseDto
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.RegisterDeviceRequestDto
import com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import com.aishtech.poslite.data.remote.dto.SubscriptionStatusResponseDto
import retrofit2.Response

/**
 * Sprint 10 — an open, fully-stubbed [PosApiService] for tests. Every method
 * errors by default; subclasses override only the endpoints they exercise. Keeps
 * the new subscription/device tests concise without repeating ~24 overrides.
 */
open class NoopPosApiService : PosApiService {
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
    override suspend fun getCurrentStock(storeId: Long?, query: String?, limit: Int?): Response<CurrentStockResponseDto> = error("unused")
    override suspend fun getProductStock(productId: Long): Response<ProductStockResponseDto> = error("unused")
    override suspend fun getDailySalesReport(storeId: Long?, date: String?, cashierId: Long?): Response<DailySalesReportResponseDto> = error("unused")
    override suspend fun getPaymentSummary(storeId: Long?, date: String?): Response<PaymentSummaryResponseDto> = error("unused")
    override suspend fun getInventoryMovementsSummary(storeId: Long?, date: String?): Response<InventoryMovementSummaryResponseDto> = error("unused")
    override suspend fun createDailyClosing(request: CreateDailyClosingRequestDto): Response<DailyClosingResponseDto> = error("unused")
    override suspend fun getDailyClosings(storeId: Long?): Response<DailyClosingListResponseDto> = error("unused")
    override suspend fun getDailyClosing(id: Long): Response<DailyClosingResponseDto> = error("unused")
    override suspend fun getSubscriptionStatus(): Response<SubscriptionStatusResponseDto> = error("unused")
    override suspend fun registerDevice(request: RegisterDeviceRequestDto): Response<RegisteredDeviceResponseDto> = error("unused")
    override suspend fun deviceHeartbeat(request: DeviceHeartbeatRequestDto): Response<RegisteredDeviceResponseDto> = error("unused")
    override suspend fun listDevices(status: String?): Response<DeviceListResponseDto> = error("unused")
    override suspend fun revokeDevice(deviceId: Long): Response<RegisteredDeviceResponseDto> = error("unused")
}
