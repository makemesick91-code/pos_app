package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/** Body for POST /api/v1/auth/login. Password is sent once, never stored. */
data class LoginRequest(
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String,
)

/** Response of POST /api/v1/auth/login. */
data class LoginResponse(
    @SerializedName("token") val token: String,
    @SerializedName("token_type") val tokenType: String?,
    @SerializedName("user") val user: UserDto?,
    @SerializedName("tenant") val tenant: TenantDto?,
    @SerializedName("store") val store: StoreDto?,
)

/** Response of GET /api/v1/auth/me. */
data class MeResponse(
    @SerializedName("user") val user: UserDto?,
    @SerializedName("tenant") val tenant: TenantDto?,
    @SerializedName("store") val store: StoreDto?,
    @SerializedName("foundation") val foundation: String?,
)

data class UserDto(
    @SerializedName("id") val id: Long,
    @SerializedName("name") val name: String?,
    @SerializedName("email") val email: String?,
    @SerializedName("role") val role: String?,
    @SerializedName("tenant_id") val tenantId: Long?,
    @SerializedName("store_id") val storeId: Long?,
)

data class TenantDto(
    @SerializedName("id") val id: Long,
    @SerializedName("name") val name: String?,
    @SerializedName("status") val status: String?,
)

data class StoreDto(
    @SerializedName("id") val id: Long,
    @SerializedName("name") val name: String?,
    @SerializedName("code") val code: String?,
)
