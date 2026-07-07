package com.aishtech.poslite.core.network

import com.aishtech.poslite.core.device.DeviceUuidProvider
import okhttp3.Interceptor
import okhttp3.Response

/**
 * Attaches `X-Device-UUID` to API calls once a device identity exists (Sprint
 * 10). The backend device.registered middleware reads this header to authorise
 * protected business APIs.
 *
 * Rules: the header is never sent before the UUID is generated, unrelated
 * headers are left untouched, and the UUID is never logged.
 */
class DeviceHeaderInterceptor(
    private val deviceUuidProvider: DeviceUuidProvider,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val uuid = deviceUuidProvider.currentDeviceUuid()
        val request = if (!uuid.isNullOrBlank()) {
            chain.request().newBuilder()
                .header(HEADER_DEVICE_UUID, uuid)
                .build()
        } else {
            chain.request()
        }

        return chain.proceed(request)
    }

    companion object {
        const val HEADER_DEVICE_UUID = "X-Device-UUID"
    }
}
