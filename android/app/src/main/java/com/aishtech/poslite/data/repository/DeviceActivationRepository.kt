package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.runtime.DeviceActivationInput
import com.aishtech.poslite.core.runtime.DeviceActivationRequestFactory
import com.aishtech.poslite.core.runtime.DeviceStatus
import com.aishtech.poslite.core.runtime.DeviceStatusMapper
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.DeviceActivationDto

/**
 * UIX-8C-07 — the device-trust repository: activate a device with an operator-
 * issued code, and poll the server-authoritative device status (UIX8C-R217/R221).
 *
 * It reuses the dormant Sprint-34 activation contract; it never marks the device
 * valid locally (validity is server-authoritative) and never logs the raw code
 * (the code lives only in the wire request, redacted by [DeviceActivationInput]).
 * The activation fingerprint is derived from the app-generated installation id —
 * never an invasive hardware identifier (UIX8C-R218).
 */
class DeviceActivationRepository(
    private val api: PosApiService,
    private val deviceUuidProvider: () -> String,
    private val installationIdProvider: () -> String,
    private val deviceLabelProvider: () -> String,
    private val appVersionName: String,
) {

    suspend fun activate(rawCode: String): ResultState<DeviceActivationDto> {
        val code = rawCode.trim()
        if (code.length < MIN_CODE_LENGTH) {
            return ResultState.Error("Kode aktivasi tidak valid.")
        }
        val installationId = installationIdProvider()
        val input = DeviceActivationInput(
            activationToken = code,
            // Non-invasive: the fingerprint is the app-generated installation id.
            deviceFingerprint = installationId,
            deviceUuid = deviceUuidProvider(),
            deviceLabel = deviceLabelProvider(),
            appVersion = appVersionName,
            installationId = installationId,
        )
        return try {
            val response = api.activateDevice(DeviceActivationRequestFactory.build(input))
            val body = response.body()
            when {
                response.isSuccessful && body?.data != null -> ResultState.Success(body.data)
                response.code() == 422 -> ResultState.Error("Kode aktivasi tidak valid.")
                response.code() == 403 -> ResultState.Error(
                    "Kode aktivasi kedaluwarsa, sudah dipakai, atau tidak cocok untuk toko ini.",
                )
                response.code() == 429 -> ResultState.Error(
                    "Terlalu banyak percobaan aktivasi. Coba lagi nanti.",
                )
                else -> ResultState.Error("Gagal mengaktifkan perangkat (kode ${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error("Tidak dapat terhubung ke server. Periksa koneksi Anda.")
        }
    }

    /**
     * Fail-closed device status poll. A transport/parse failure or a non-2xx
     * response yields [DeviceStatus.unknown] (NOT active) so the startup state
     * machine never treats an unreachable server as a valid device (UIX8C-R214/R221).
     */
    suspend fun status(): DeviceStatus = try {
        val response = api.deviceStatus()
        if (response.isSuccessful) DeviceStatusMapper.map(response.body()) else DeviceStatus.unknown()
    } catch (e: Exception) {
        DeviceStatus.unknown()
    }

    private companion object {
        const val MIN_CODE_LENGTH = 8
    }
}
