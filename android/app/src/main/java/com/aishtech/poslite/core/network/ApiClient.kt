package com.aishtech.poslite.core.network

import com.aishtech.poslite.BuildConfig
import com.aishtech.poslite.core.config.AppConfig
import com.aishtech.poslite.core.device.DeviceUuidProvider
import com.aishtech.poslite.core.session.TokenStore
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Builds the Retrofit [PosApiService]. The [AuthInterceptor] adds the bearer
 * token; the logging interceptor is only attached in debug builds and never
 * logs the Authorization header body content beyond what OkHttp redacts.
 */
object ApiClient {

    fun create(
        tokenStore: TokenStore,
        deviceUuidProvider: DeviceUuidProvider? = null,
        baseUrl: String = AppConfig.DEFAULT_API_BASE_URL,
    ): PosApiService {
        val builder = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .addInterceptor(AuthInterceptor(tokenStore))

        // Sprint 10 — attach X-Device-UUID once a device identity exists.
        if (deviceUuidProvider != null) {
            builder.addInterceptor(DeviceHeaderInterceptor(deviceUuidProvider))
        }

        // UIX-7 (UIX7-R026/R047) — HTTP logging is attached ONLY for the emulator
        // `debug` build type, never for `pilot` or `release`. The `pilot` variant is
        // debuggable (so BuildConfig.DEBUG is true), which is why the gate is the
        // build-type name, not BuildConfig.DEBUG: no request/response logging runs
        // against the live pilot/production backend. Even in debug the level is
        // BASIC (no headers/body) and Authorization is redacted.
        if (BuildConfig.BUILD_TYPE == "debug") {
            val logging = HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BASIC
            }
            // Never log the bearer token in any build.
            logging.redactHeader("Authorization")
            builder.addInterceptor(logging)
        }

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(builder.build())
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(PosApiService::class.java)
    }
}
