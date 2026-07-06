package com.aishtech.poslite.core.config

/**
 * Static app configuration for Sprint 3.
 *
 * The API base URL must be easy to swap for local/pilot environments and must
 * never embed a production secret or token. `10.0.2.2` is the Android emulator
 * alias for the host machine's `localhost` (the Laravel dev server).
 */
object AppConfig {
    const val DEFAULT_API_BASE_URL = "http://10.0.2.2:8000/"
    const val FOUNDATION = "POS_ANDROID_SAAS_FOUNDATION"
    const val APP_NAME = "Aish POS Lite"

    /** Local product search must stay bounded for older Android devices. */
    const val SEARCH_RESULT_LIMIT = 50
    const val PRODUCT_LIST_LIMIT = 200
}
