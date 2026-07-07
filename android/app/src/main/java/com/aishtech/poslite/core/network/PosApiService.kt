package com.aishtech.poslite.core.network

import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateDailyClosingRequestDto
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto
import com.aishtech.poslite.data.remote.dto.DailyClosingListResponseDto
import com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto
import com.aishtech.poslite.data.remote.dto.DailySalesReportResponseDto
import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryResponseDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.PaymentSummaryResponseDto
import com.aishtech.poslite.data.remote.dto.ProductStockResponseDto
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * Retrofit contract for the Aish POS backend (Sprint 1 auth + Sprint 2 sync).
 * Sprint 3 consumes these endpoints; it never calls payment gateways.
 */
interface PosApiService {

    @POST("api/v1/auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("api/v1/auth/me")
    suspend fun me(): Response<MeResponse>

    @POST("api/v1/auth/logout")
    suspend fun logout(): Response<Unit>

    @GET("api/v1/sync/products")
    suspend fun syncProducts(
        @Query("updated_since") updatedSince: String? = null,
        @Query("store_id") storeId: Long? = null,
    ): Response<ProductSyncResponse>

    @GET("api/v1/sync/categories")
    suspend fun syncCategories(
        @Query("updated_since") updatedSince: String? = null,
        @Query("store_id") storeId: Long? = null,
    ): Response<CategorySyncResponse>

    // Sprint 4 — sales submission + online CASH checkout. The app never calls a
    // payment gateway; CASH is finalized by the backend.
    @POST("api/v1/sales")
    suspend fun createSale(@Body request: CreateSaleRequestDto): Response<SaleResponse>

    @GET("api/v1/sales/{id}")
    suspend fun getSale(@Path("id") id: Long): Response<SaleResponse>

    @POST("api/v1/sales/{id}/cancel")
    suspend fun cancelSale(@Path("id") id: Long): Response<SaleResponse>

    // Sprint 5 — backend-driven QRIS. The app requests a QRIS payment for a sale
    // and polls its status; it never contacts a payment gateway or holds any
    // gateway credential.
    @POST("api/v1/sales/{id}/payments/qris")
    suspend fun createQrisPayment(
        @Path("id") saleId: Long,
        @Body request: CreateQrisPaymentRequestDto,
    ): Response<QrisPaymentResponse>

    @GET("api/v1/payments/{id}/status")
    suspend fun getPaymentStatus(@Path("id") paymentId: Long): Response<QrisPaymentResponse>

    // Sprint 6 — tenant-isolated receipt preview. The backend owns receipt data
    // and print eligibility; the app only formats an approved payload for ESC/POS.
    @GET("api/v1/sales/{id}/receipt")
    suspend fun getReceipt(@Path("id") saleId: Long): Response<ReceiptResponseDto>

    // Sprint 8 — read-only inventory stock. The backend derives stock from the
    // ledger; the app only reads it for lightweight display and never mutates it.
    @GET("api/v1/inventory/current-stock")
    suspend fun getCurrentStock(
        @Query("store_id") storeId: Long? = null,
        @Query("q") query: String? = null,
        @Query("limit") limit: Int? = null,
    ): Response<CurrentStockResponseDto>

    @GET("api/v1/inventory/products/{product}/stock")
    suspend fun getProductStock(@Path("product") productId: Long): Response<ProductStockResponseDto>

    // Sprint 9 — reports & closing foundation. All figures are backend-computed;
    // the app only displays summaries and requests a daily close. It never
    // calculates authoritative totals.
    @GET("api/v1/reports/daily-sales")
    suspend fun getDailySalesReport(
        @Query("store_id") storeId: Long? = null,
        @Query("date") date: String? = null,
        @Query("cashier_id") cashierId: Long? = null,
    ): Response<DailySalesReportResponseDto>

    @GET("api/v1/reports/payment-summary")
    suspend fun getPaymentSummary(
        @Query("store_id") storeId: Long? = null,
        @Query("date") date: String? = null,
    ): Response<PaymentSummaryResponseDto>

    @GET("api/v1/reports/inventory-movements-summary")
    suspend fun getInventoryMovementsSummary(
        @Query("store_id") storeId: Long? = null,
        @Query("date") date: String? = null,
    ): Response<InventoryMovementSummaryResponseDto>

    @POST("api/v1/closings/daily")
    suspend fun createDailyClosing(
        @Body request: CreateDailyClosingRequestDto,
    ): Response<DailyClosingResponseDto>

    @GET("api/v1/closings/daily")
    suspend fun getDailyClosings(
        @Query("store_id") storeId: Long? = null,
    ): Response<DailyClosingListResponseDto>

    @GET("api/v1/closings/daily/{id}")
    suspend fun getDailyClosing(@Path("id") id: Long): Response<DailyClosingResponseDto>
}
