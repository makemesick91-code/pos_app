package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CreateDailyClosingRequestDto
import com.aishtech.poslite.data.remote.dto.DailyClosingDto

/**
 * Creates and reads daily closing snapshots (Sprint 9). The closing snapshot and
 * all of its totals are computed and owned by the backend; this repository only
 * sends the store + business date and relays the returned snapshot. A duplicate
 * close (same tenant/store/business date) is surfaced via `duplicateReplay` so
 * the UI can show "already closed" instead of an error.
 */
class ClosingRepository(private val api: PosApiService) {

    /** Result of a close request: the snapshot plus whether it replayed an existing one. */
    data class ClosingResult(
        val closing: DailyClosingDto,
        val duplicateReplay: Boolean,
    )

    suspend fun createClosing(
        storeId: Long?,
        businessDate: String,
        notes: String? = null,
    ): ResultState<ClosingResult> {
        return try {
            val response = api.createDailyClosing(
                CreateDailyClosingRequestDto(
                    storeId = storeId,
                    businessDate = businessDate,
                    notes = notes,
                ),
            )
            val body = response.body()
            if (response.isSuccessful && body?.data != null) {
                ResultState.Success(
                    ClosingResult(
                        closing = body.data,
                        duplicateReplay = body.meta?.duplicateReplay ?: false,
                    ),
                )
            } else {
                ResultState.Error("Gagal menutup hari (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal menutup hari. Periksa koneksi.")
        }
    }

    suspend fun listClosings(storeId: Long? = null): ResultState<List<DailyClosingDto>> {
        return try {
            val response = api.getDailyClosings(storeId = storeId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat daftar closing (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat daftar closing. Periksa koneksi.")
        }
    }

    suspend fun getClosing(id: Long): ResultState<DailyClosingDto> {
        return try {
            val response = api.getDailyClosing(id)
            val body = response.body()
            if (response.isSuccessful && body?.data != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat closing (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat closing. Periksa koneksi.")
        }
    }
}
