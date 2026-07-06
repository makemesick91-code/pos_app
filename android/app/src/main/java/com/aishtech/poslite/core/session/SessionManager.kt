package com.aishtech.poslite.core.session

/**
 * Session facade over [TokenStore]. Holds the auth token and lightweight,
 * non-sensitive identity hints (display name / store). Never stores passwords
 * or payment credentials.
 */
class SessionManager(private val tokenStore: TokenStore) {

    fun startSession(token: String) = tokenStore.saveToken(token)

    fun token(): String? = tokenStore.getToken()

    fun isLoggedIn(): Boolean = tokenStore.isLoggedIn()

    fun endSession() = tokenStore.clearToken()
}
