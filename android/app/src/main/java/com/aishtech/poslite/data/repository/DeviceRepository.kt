package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.device.DeviceIdentityStore
import com.aishtech.poslite.core.device.DeviceInfoProvider
import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.DeviceHeartbeatRequestDto
import com.aishtech.poslite.data.remote.dto.RegisterDeviceRequestDto
import com.aishtech.poslite.data.remote.dto.RegisteredDeviceDto
import com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto

/**
 * Backend-approved device registration + heartbeat (Sprint 10). The device UUID
 * is generated/stored locally by [DeviceIdentityStore]; registration and the
 * device limit are always enforced by the backend. This repository never
 * bypasses those checks.
 */
class DeviceRepository(
    private val api: PosApiService,
    private val identityStore: DeviceIdentityStore,
) {

    /** Ensures a local UUID exists and registers this device with the backend. */
    suspend fun registerCurrentDevice(storeId: Long? = null): ResultState<RegisteredDeviceResult> {
        val uuid = identityStore.getOrCreateDeviceUuid()
        return try {
            val response = api.registerDevice(
                RegisterDeviceRequestDto(
                    deviceUuid = uuid,
                    deviceName = DeviceInfoProvider.deviceName(),
                    platform = DeviceInfoProvider.PLATFORM_ANDROID,
                    appVersion = DeviceInfoProvider.appVersion(),
                    storeId = storeId,
                ),
            )
            mapDevice(response)
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Tidak dapat mendaftarkan perangkat.")
        }
    }

    suspend fun heartbeat(): ResultState<RegisteredDeviceResult> {
        val uuid = identityStore.currentDeviceUuid()
            ?: return ResultState.Error("Perangkat belum terdaftar.")
        return try {
            val response = api.deviceHeartbeat(
                DeviceHeartbeatRequestDto(deviceUuid = uuid, appVersion = DeviceInfoProvider.appVersion()),
            )
            mapDevice(response)
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal mengirim heartbeat perangkat.")
        }
    }

    suspend fun listDevices(status: String? = null): ResultState<List<RegisteredDeviceDto>> {
        return try {
            val response = api.listDevices(status)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat daftar perangkat (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat daftar perangkat.")
        }
    }

    private fun mapDevice(response: retrofit2.Response<RegisteredDeviceResponseDto>): ResultState<RegisteredDeviceResult> {
        val body = response.body()
        return if (response.isSuccessful && body?.data != null) {
            ResultState.Success(
                RegisteredDeviceResult(
                    device = body.data,
                    existingDevice = body.meta?.existingDevice ?: false,
                ),
            )
        } else if (response.code() == 403 || response.code() == 402) {
            // Device limit reached / subscription blocked / revoked — surface as a
            // clear blocked state; never treat it as success.
            ResultState.Error("Perangkat tidak dapat diaktifkan (${response.code()}).")
        } else {
            ResultState.Error("Gagal mendaftarkan perangkat (${response.code()}).")
        }
    }

    data class RegisteredDeviceResult(
        val device: RegisteredDeviceDto,
        val existingDevice: Boolean,
    )
}
