package com.aishtech.poslite

import com.aishtech.poslite.core.session.AuthEventInterceptor
import com.aishtech.poslite.core.session.SessionEvent
import com.aishtech.poslite.core.session.SessionEventBus
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import okhttp3.Call
import okhttp3.Connection
import okhttp3.Interceptor
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.concurrent.TimeUnit

/**
 * UIX-8C-07 — the 401 → SessionExpired signal (UIX8C-R233). The interceptor emits
 * a single [SessionEvent.Expired] on a 401 and nothing on a 2xx; it never mutates
 * the request nor clears the session (recovery is the state machine's job).
 */
class SessionEventsTest {

    private class FakeChain(private val code: Int) : Interceptor.Chain {
        private val request = Request.Builder().url("http://localhost/api").build()
        override fun request(): Request = request
        override fun proceed(request: Request): Response = Response.Builder()
            .request(request)
            .protocol(Protocol.HTTP_1_1)
            .code(code)
            .message(if (code == 401) "Unauthorized" else "OK")
            .body("{}".toResponseBody(null))
            .build()

        override fun connection(): Connection? = null
        override fun call(): Call = throw NotImplementedError()
        override fun connectTimeoutMillis(): Int = 0
        override fun withConnectTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
        override fun readTimeoutMillis(): Int = 0
        override fun withReadTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
        override fun writeTimeoutMillis(): Int = 0
        override fun withWriteTimeout(timeout: Int, unit: TimeUnit): Interceptor.Chain = this
    }

    @Test
    fun `401 emits a single Expired event`() = runTest {
        val bus = SessionEventBus()
        val received = mutableListOf<SessionEvent>()
        backgroundScope.launch { bus.events.collect { received += it } }
        runCurrent()

        AuthEventInterceptor(bus).intercept(FakeChain(401))
        runCurrent()

        assertTrue(received.contains(SessionEvent.Expired))
    }

    @Test
    fun `2xx emits nothing`() = runTest {
        val bus = SessionEventBus()
        val received = mutableListOf<SessionEvent>()
        backgroundScope.launch { bus.events.collect { received += it } }
        runCurrent()

        AuthEventInterceptor(bus).intercept(FakeChain(200))
        runCurrent()

        assertFalse(received.contains(SessionEvent.Expired))
    }

    @Test
    fun `bus delivers device revoked events with reason`() = runTest {
        val bus = SessionEventBus()
        val received = mutableListOf<SessionEvent>()
        backgroundScope.launch { bus.events.collect { received += it } }
        runCurrent()

        bus.emit(SessionEvent.DeviceRevoked("hilang"))
        runCurrent()

        assertTrue(received.any { it is SessionEvent.DeviceRevoked && it.reason == "hilang" })
    }
}
