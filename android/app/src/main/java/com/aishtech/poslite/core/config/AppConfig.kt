package com.aishtech.poslite.core.config

import com.aishtech.poslite.BuildConfig

/**
 * Static app configuration for Sprint 3.
 *
 * The API base URL is build-typed (UIX-7): debug/dev builds use the emulator
 * host alias `10.0.2.2`, while release/pilot builds default to the HTTPS pilot
 * backend (`https://aishpos.online/`). It never embeds a production secret or
 * token — the value comes from [BuildConfig.API_BASE_URL] (see build.gradle.kts).
 */
object AppConfig {
    val DEFAULT_API_BASE_URL: String = BuildConfig.API_BASE_URL
    const val FOUNDATION = "POS_ANDROID_SAAS_FOUNDATION"
    const val APP_NAME = "Aish POS Lite"

    /** Local product search must stay bounded for older Android devices. */
    const val SEARCH_RESULT_LIMIT = 50
    const val PRODUCT_LIST_LIMIT = 200
}
