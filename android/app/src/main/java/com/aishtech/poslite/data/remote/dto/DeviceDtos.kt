package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/** Body for POST /api/v1/devices/register (Sprint 10). */
data class RegisterDeviceRequestDto(
    @SerializedName("device_uuid") val deviceUuid: String,
    @SerializedName("device_name") val deviceName: String?,
    @SerializedName("platform") val platform: String,
    @SerializedName("app_version") val appVersion: String?,
    @SerializedName("store_id") val storeId: Long?,
)

/** Body for POST /api/v1/devices/heartbeat (Sprint 10). */
data class DeviceHeartbeatRequestDto(
    @SerializedName("device_uuid") val deviceUuid: String,
    @SerializedName("app_version") val appVersion: String?,
)

/** Response of register/heartbeat/revoke — a single device. */
data class RegisteredDeviceResponseDto(
    @SerializedName("data") val data: RegisteredDeviceDto?,
    @SerializedName("meta") val meta: DeviceMetaDto?,
)

/** Response of GET /api/v1/devices — the tenant's own devices. */
data class DeviceListResponseDto(
    @SerializedName("data") val data: List<RegisteredDeviceDto> = emptyList(),
)

data class RegisteredDeviceDto(
    @SerializedName("id") val id: Long,
    @SerializedName("device_uuid") val deviceUuid: String?,
    @SerializedName("device_name") val deviceName: String?,
    @SerializedName("platform") val platform: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("registered_at") val registeredAt: String?,
    @SerializedName("last_seen_at") val lastSeenAt: String?,
)

data class DeviceMetaDto(
    @SerializedName("existing_device") val existingDevice: Boolean?,
)
