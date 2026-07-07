package com.aishtech.poslite.core.device

import android.os.Build
import com.aishtech.poslite.BuildConfig

/**
 * Optional, non-sensitive display metadata for device registration (Sprint 10).
 * Uses only the public Build manufacturer/model for a friendly device name and
 * the app's own version. It never reads a hardware serial, IMEI, or any privacy
 * sensitive identifier.
 */
object DeviceInfoProvider {

    const val PLATFORM_ANDROID = "ANDROID"

    fun deviceName(): String =
        // Build fields are platform types that can be null in JVM unit tests;
        // listOfNotNull + blank-filter keeps this null-safe on and off device.
        listOfNotNull(Build.MANUFACTURER, Build.MODEL)
            .filter { it.isNotBlank() }
            .joinToString(" ")
            .ifBlank { "Android Device" }

    fun appVersion(): String = BuildConfig.VERSION_NAME
}
