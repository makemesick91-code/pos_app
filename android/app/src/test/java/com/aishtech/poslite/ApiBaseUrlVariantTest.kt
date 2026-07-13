package com.aishtech.poslite

import com.aishtech.poslite.core.config.AppConfig
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-7 (UIX7-R045/R046/R049) — variant-aware endpoint contract.
 *
 * This test compiles once per build variant and runs against that variant's
 * generated [BuildConfig], so `testDebugUnitTest`, `testPilotUnitTest`, and
 * `testReleaseUnitTest` each assert the endpoint actually baked into their own
 * APK — the strongest machine check that a physical-device (pilot) or
 * production (release) build can never ship the emulator host alias.
 *
 * Contract:
 *   debug   -> http://10.0.2.2:8000/   (emulator dev only)
 *   pilot   -> https://aishpos.online/ (physical-device pilot)
 *   release -> https://aishpos.online/ (production)
 */
class ApiBaseUrlVariantTest {

    @Test
    fun appConfigMirrorsBuildConfigEndpoint() {
        // AppConfig is the single read path the app uses; it must not fork the URL.
        assertEquals(BuildConfig.API_BASE_URL, AppConfig.DEFAULT_API_BASE_URL)
    }

    @Test
    fun baseUrlKeepsTrailingSlashForRetrofit() {
        assertTrue(
            "Retrofit base URL must end with '/': ${BuildConfig.API_BASE_URL}",
            BuildConfig.API_BASE_URL.endsWith("/"),
        )
    }

    @Test
    fun endpointMatchesVariantContract() {
        when (BuildConfig.BUILD_TYPE) {
            "debug" -> assertEquals(
                "http://10.0.2.2:8000/",
                BuildConfig.API_BASE_URL,
            )
            "pilot", "release" -> {
                assertEquals(
                    "Non-debug variant must use the governed HTTPS pilot backend",
                    "https://aishpos.online/",
                    BuildConfig.API_BASE_URL,
                )
            }
            else -> throw AssertionError("Unexpected build type: ${BuildConfig.BUILD_TYPE}")
        }
    }

    @Test
    fun pilotAndReleaseNeverContainEmulatorOrLocalOrCleartextHost() {
        if (BuildConfig.BUILD_TYPE == "debug") return

        val url = BuildConfig.API_BASE_URL
        for (forbidden in listOf(
            "10.0.2.2",
            "localhost",
            "127.0.0.1",
            "http://",              // no cleartext scheme
            "aishpos.online:8080",  // no raw pilot port
        )) {
            assertFalse(
                "Pilot/release endpoint must not contain '$forbidden': $url",
                url.contains(forbidden),
            )
        }
        assertTrue("Pilot/release endpoint must be HTTPS: $url", url.startsWith("https://"))
    }
}
