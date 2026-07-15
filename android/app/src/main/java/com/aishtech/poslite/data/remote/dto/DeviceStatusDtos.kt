package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * UIX-8C-07 — response of GET /api/v1/android/device/status (UIX8C-R221).
 *
 * The server is the sole authority on device validity. This DTO is a truthful
 * posture the Android startup state machine polls: it carries active/revoked +
 * a human-safe reason and safe tenant/outlet/device labels — never a token,
 * fingerprint, or installation hash.
 */
data class DeviceStatusResponseDto(
    @SerializedName("data") val data: DeviceStatusDto?,
)

data class DeviceStatusDto(
    @SerializedName("status") val status: String?,
    @SerializedName("active") val active: Boolean?,
    @SerializedName("revoked") val revoked: Boolean?,
    @SerializedName("revocation_reason") val revocationReason: String?,
    @SerializedName("tenant") val tenant: DeviceStatusTenantDto?,
    @SerializedName("outlet") val outlet: DeviceStatusOutletDto?,
    @SerializedName("device_name") val deviceName: String?,
    @SerializedName("app_version") val appVersion: String?,
    @SerializedName("activated_at") val activatedAt: String?,
    @SerializedName("last_seen_at") val lastSeenAt: String?,
)

data class DeviceStatusTenantDto(
    @SerializedName("id") val id: Long?,
    @SerializedName("name") val name: String?,
)

data class DeviceStatusOutletDto(
    @SerializedName("id") val id: Long?,
    @SerializedName("name") val name: String?,
)
