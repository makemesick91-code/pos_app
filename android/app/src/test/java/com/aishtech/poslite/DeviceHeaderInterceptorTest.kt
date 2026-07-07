package com.aishtech.poslite

import com.aishtech.poslite.core.device.DeviceUuidProvider
import com.aishtech.poslite.core.network.DeviceHeaderInterceptor
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.concurrent.TimeUnit

/**
 * Sprint 10 — the interceptor attaches X-Device-UUID only when an identity
 * exists, and never disturbs unrelated headers.
 */
class DeviceHeaderInterceptorTest {

    /** Minimal chain that records the request passed to proceed(). */
    private class RecordingChain(private val request: Request) : Interceptor.Chain {
        var proceeded: Request? = null
        override fun request(): Request = request
        override fun proceed(request: Request): Response {
            proceeded = request
            return Response.Builder()
                .request(request)
                .protocol(Protocol.HTTP_1_1)
                .code(200)
                .message("OK")
                .body("".toResponseBody(null))
                .build()
        }
        override fun connection() = null
        override fun call() = OkHttpClient().newCall(request)
        override fun connectTimeoutMillis() = 0
        override fun withConnectTimeout(timeout: Int, unit: TimeUnit) = this
        override fun readTimeoutMillis() = 0
        override fun withReadTimeout(timeout: Int, unit: TimeUnit) = this
        override fun writeTimeoutMillis() = 0
        override fun withWriteTimeout(timeout: Int, unit: TimeUnit) = this
    }

    private fun baseRequest(): Request =
        Request.Builder().url("http://example.test/api").header("Accept", "application/json").build()

    @Test
    fun `adds X-Device-UUID when uuid exists`() {
        val interceptor = DeviceHeaderInterceptor(DeviceUuidProvider { "device-123" })
        val chain = RecordingChain(baseRequest())

        interceptor.intercept(chain)

        assertEquals("device-123", chain.proceeded?.header("X-Device-UUID"))
    }

    @Test
    fun `does not add header when uuid missing`() {
        val interceptor = DeviceHeaderInterceptor(DeviceUuidProvider { null })
        val chain = RecordingChain(baseRequest())

        interceptor.intercept(chain)

        assertNull(chain.proceeded?.header("X-Device-UUID"))
    }

    @Test
    fun `does not alter unrelated headers`() {
        val interceptor = DeviceHeaderInterceptor(DeviceUuidProvider { "device-123" })
        val chain = RecordingChain(baseRequest())

        interceptor.intercept(chain)

        assertEquals("application/json", chain.proceeded?.header("Accept"))
    }
}
