package com.aishtech.poslite.core.session

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import okhttp3.Interceptor
import okhttp3.Response

/**
 * UIX-8C-07 — process-wide session lifecycle signals (UIX8C-R233/R234). A backend
 * 401 anywhere in the OkHttp stack is turned into a single [SessionEvent.Expired]
 * so the app can lock the UI and require re-authentication WITHOUT deleting
 * pending offline transactions. Device-invalid/revoked signals are surfaced the
 * same way. These are one-shot signals, not authority — the startup state machine
 * re-validates against the server before returning to `Ready`.
 */
sealed interface SessionEvent {
    /** The bearer token was rejected (HTTP 401). Re-authentication required. */
    data object Expired : SessionEvent

    /** The server reported the device as revoked (fail closed). */
    data class DeviceRevoked(val reason: String?) : SessionEvent

    /** The device is no longer valid/registered (fail closed). */
    data object DeviceInvalid : SessionEvent
}

/**
 * A lightweight app-scoped bus. `extraBufferCapacity` lets a non-suspending
 * `tryEmit` from the OkHttp thread never block or drop the signal.
 */
class SessionEventBus {
    private val _events = MutableSharedFlow<SessionEvent>(
        replay = 0,
        extraBufferCapacity = 8,
    )
    val events: SharedFlow<SessionEvent> = _events.asSharedFlow()

    fun emit(event: SessionEvent) {
        _events.tryEmit(event)
    }
}

/**
 * Emits [SessionEvent.Expired] on a 401. It never mutates the request, never
 * logs the token, and never itself clears the session — clearing/recovery is the
 * state machine's job so pending transactions stay protected (UIX8C-R229/R233).
 */
class AuthEventInterceptor(private val bus: SessionEventBus) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val response = chain.proceed(chain.request())
        if (response.code == HTTP_UNAUTHORIZED) {
            bus.emit(SessionEvent.Expired)
        }
        return response
    }

    private companion object {
        const val HTTP_UNAUTHORIZED = 401
    }
}
