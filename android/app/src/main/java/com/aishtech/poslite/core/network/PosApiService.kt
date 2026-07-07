package com.aishtech.poslite.core.network

import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
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
}
