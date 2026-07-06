package com.aishtech.poslite.core.network

import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
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
}
