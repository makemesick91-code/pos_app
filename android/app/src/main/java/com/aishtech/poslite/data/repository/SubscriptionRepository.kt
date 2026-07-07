package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.SubscriptionStatusDto

/**
 * Reads the backend-computed subscription status (Sprint 10). The backend is the
 * sole authority for the allowed/blocked decision; this repository only fetches
 * it — it never fabricates an allowed state, even on network error.
 */
class SubscriptionRepository(private val api: PosApiService) {

    suspend fun getStatus(): ResultState<SubscriptionStatusDto> {
        return try {
            val response = api.getSubscriptionStatus()
            val body = response.body()
            if (response.isSuccessful && body?.data != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat status langganan (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat status langganan. Periksa koneksi.")
        }
    }
}
