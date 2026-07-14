package com.aishtech.poslite

import android.bluetooth.BluetoothAdapter
import android.os.Build
import com.aishtech.poslite.feature.printer.BluetoothPrinterConnection
import com.aishtech.poslite.feature.printer.PrintResult
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * FIX-ANDROID-BLUETOOTH-SCAN-PERMISSION-LINT — permission contract regression.
 *
 * Roots the fix in tests rather than a lint suppression. Verifies the Android
 * API-level BLUETOOTH_CONNECT contract and, crucially, that a denied permission
 * NEVER reaches a protected Bluetooth API (the class no longer calls the
 * BLUETOOTH_SCAN-requiring `cancelDiscovery`, and never touches the adapter
 * before the permission gate passes). Uses the Context-free internal
 * constructor so the whole contract is exercised on the JVM without a device or
 * Robolectric.
 *
 * Not covered here (requires real hardware / instrumentation — BluetoothAdapter
 * is final and cannot be constructed on the JVM): the adapter-enabled check, a
 * successful socket connect, and a live SecurityException from the framework.
 * Those are asserted at the seam boundary via injected probes instead.
 */
class BluetoothPrinterConnectionTest {

    private fun connection(
        sdkInt: Int,
        connectGranted: () -> Boolean,
        adapterProvider: () -> BluetoothAdapter?,
    ) = BluetoothPrinterConnection(
        sdkInt = sdkInt,
        connectPermissionGranted = connectGranted,
        adapterProvider = adapterProvider,
    )

    @Test
    fun api31_permissionDenied_returnsActionableFailure_andNeverTouchesAdapter() = runTest {
        var adapterProbed = false
        var permissionChecked = false
        val conn = connection(
            sdkInt = Build.VERSION_CODES.S,
            connectGranted = { permissionChecked = true; false },
            adapterProvider = { adapterProbed = true; null },
        )

        val result = conn.print("00:11:22:33:44:55", byteArrayOf(0x1B))

        assertEquals(PrintResult.Failure("Izin Bluetooth belum diberikan."), result)
        assertTrue("BLUETOOTH_CONNECT must be checked at runtime on API 31+", permissionChecked)
        assertFalse("A protected Bluetooth API must not be reached when permission is denied", adapterProbed)
    }

    @Test
    fun api31_permissionGranted_passesGate_andReachesAdapter() = runTest {
        var adapterProbed = false
        val conn = connection(
            sdkInt = Build.VERSION_CODES.S,
            connectGranted = { true },
            adapterProvider = { adapterProbed = true; null },
        )

        val result = conn.print("00:11:22:33:44:55", byteArrayOf(0x1B))

        // Adapter resolves to null on a device without Bluetooth -> truthful failure.
        assertEquals(PrintResult.Failure("Perangkat tidak mendukung Bluetooth."), result)
        assertTrue("A granted permission must let the flow reach the adapter", adapterProbed)
    }

    @Test
    fun api30_legacyPath_skipsRuntimeCheck_andReachesAdapter() = runTest {
        var permissionChecked = false
        var adapterProbed = false
        val conn = connection(
            sdkInt = Build.VERSION_CODES.R, // API 30
            connectGranted = { permissionChecked = true; false },
            adapterProvider = { adapterProbed = true; null },
        )

        val result = conn.print("00:11:22:33:44:55", byteArrayOf(0x1B))

        assertEquals(PrintResult.Failure("Perangkat tidak mendukung Bluetooth."), result)
        assertFalse("Legacy (<=API 30) must not consult the API 31+ runtime permission", permissionChecked)
        assertTrue(adapterProbed)
    }

    @Test
    fun api26_minSupported_usesLegacyPath() = runTest {
        var permissionChecked = false
        val conn = connection(
            sdkInt = Build.VERSION_CODES.O, // API 26 (minSdk)
            connectGranted = { permissionChecked = true; false },
            adapterProvider = { null },
        )

        val result = conn.print("00:11:22:33:44:55", byteArrayOf(0x1B))

        assertEquals(PrintResult.Failure("Perangkat tidak mendukung Bluetooth."), result)
        assertFalse(permissionChecked)
    }

    @Test
    fun securityException_isHandledSafely_neverCrashes() = runTest {
        val conn = connection(
            sdkInt = Build.VERSION_CODES.S,
            connectGranted = { true },
            adapterProvider = { throw SecurityException("permission revoked mid-flight") },
        )

        val result = conn.print("00:11:22:33:44:55", byteArrayOf(0x1B))

        assertEquals(PrintResult.Failure("Izin Bluetooth ditolak."), result)
    }

    @Test
    fun invalidAddress_surfacesActionableFailure() = runTest {
        val conn = connection(
            sdkInt = Build.VERSION_CODES.S,
            connectGranted = { true },
            adapterProvider = { throw IllegalArgumentException("bad MAC") },
        )

        val result = conn.print("not-a-mac", byteArrayOf(0x1B))

        assertEquals(PrintResult.Failure("Alamat printer tidak valid."), result)
    }
}
