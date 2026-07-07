package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/** Response of GET /api/v1/subscription/status (Sprint 10). */
data class SubscriptionStatusResponseDto(
    @SerializedName("data") val data: SubscriptionStatusDto?,
)

/**
 * Backend-computed subscription decision. `allowed` is authoritative — Android
 * must never trust a client-side status.
 */
data class SubscriptionStatusDto(
    @SerializedName("allowed") val allowed: Boolean,
    @SerializedName("status") val status: String?,
    @SerializedName("reason") val reason: String?,
    @SerializedName("plan") val plan: SubscriptionPlanDto?,
    @SerializedName("devices") val devices: DeviceLimitDto?,
)

data class SubscriptionPlanDto(
    @SerializedName("code") val code: String?,
    @SerializedName("name") val name: String?,
    @SerializedName("max_devices") val maxDevices: Int?,
    @SerializedName("max_stores") val maxStores: Int?,
    @SerializedName("max_products") val maxProducts: Int?,
)

data class DeviceLimitDto(
    @SerializedName("active_count") val activeCount: Int?,
    @SerializedName("max_devices") val maxDevices: Int?,
)
