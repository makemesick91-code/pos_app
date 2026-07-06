package com.aishtech.poslite.core.session

import android.content.Context
import android.content.SharedPreferences

/**
 * Persists the Sanctum bearer token issued by `/api/v1/auth/login`.
 *
 * Sprint 3 uses plain SharedPreferences as an acceptable fallback.
 * TODO(secure-storage): migrate to EncryptedSharedPreferences / Keystore
 * hardening once the dependency is stabilised for the target devices.
 *
 * Rule: the user password is NEVER stored here (or anywhere on device).
 */
interface TokenStore {
    fun saveToken(token: String)
    fun getToken(): String?
    fun clearToken()
    fun isLoggedIn(): Boolean
}

class SharedPrefsTokenStore(context: Context) : TokenStore {

    private val prefs: SharedPreferences =
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    override fun saveToken(token: String) {
        prefs.edit().putString(KEY_TOKEN, token).apply()
    }

    override fun getToken(): String? = prefs.getString(KEY_TOKEN, null)

    override fun clearToken() {
        prefs.edit().remove(KEY_TOKEN).apply()
    }

    override fun isLoggedIn(): Boolean = !getToken().isNullOrBlank()

    private companion object {
        const val PREFS_NAME = "aish_pos_session"
        const val KEY_TOKEN = "auth_token"
    }
}
