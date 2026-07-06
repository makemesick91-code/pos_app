package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.session.SessionManager
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse

/**
 * Consumes the Sprint 1 auth API. On success the bearer token is persisted via
 * [SessionManager]; the password is used only for the request and never stored.
 */
class AuthRepository(
    private val api: PosApiService,
    private val session: SessionManager,
) {

    suspend fun login(email: String, password: String): ResultState<LoginResponse> {
        return try {
            val response = api.login(LoginRequest(email = email.trim(), password = password))
            val body = response.body()
            if (response.isSuccessful && body != null) {
                session.startSession(body.token)
                ResultState.Success(body)
            } else if (response.code() == 422) {
                ResultState.Error("Email atau kata sandi salah.")
            } else {
                ResultState.Error("Gagal masuk (kode ${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error("Tidak dapat terhubung ke server.")
        }
    }

    suspend fun logout() {
        try {
            api.logout()
        } catch (_: Exception) {
            // Best-effort remote revoke; local session is cleared regardless.
        } finally {
            session.endSession()
        }
    }

    fun isLoggedIn(): Boolean = session.isLoggedIn()
}
