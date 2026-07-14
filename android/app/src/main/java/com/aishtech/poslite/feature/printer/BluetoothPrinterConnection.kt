package com.aishtech.poslite.feature.printer

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.IOException
import java.util.UUID

/**
 * Android-native, lightweight Bluetooth SPP printer transport (Sprint 6
 * foundation). It connects to an already-paired thermal printer by MAC address
 * over the standard Serial Port Profile UUID and streams the ESC/POS bytes.
 *
 * Scope guard: no discovery, no vendor SDK. The printer is selected from a
 * manually-entered / paired MAC in printer settings.
 *
 * Permission contract (see .claude/rules/58-android-bluetooth-permission-foundation.md):
 * because this transport never starts device discovery, it MUST NOT call the
 * scan/discovery APIs (`startDiscovery` / `cancelDiscovery`) and therefore never
 * requires the dangerous `BLUETOOTH_SCAN` permission. Its only protected calls
 * (`createRfcommSocketToServiceRecord`, `connect`) need `BLUETOOTH_CONNECT`,
 * which is a runtime permission on Android 12+ (API 31) and covered by the
 * legacy install-time `BLUETOOTH` permission on API 30 and below. The manifest
 * declares exactly those permissions and deliberately omits `BLUETOOTH_SCAN`.
 *
 * All work runs off the main thread and every failure — including a denied
 * permission or a mid-flight [SecurityException] — is surfaced as
 * [PrintResult.Failure] with an actionable message, never a crash and never a
 * fabricated success.
 *
 * The primary constructor takes Context-free capability seams so the API-level
 * permission contract is unit-testable on the JVM without an Android device; the
 * public [constructor] wires the real Android probes.
 */
class BluetoothPrinterConnection internal constructor(
    private val sdkInt: Int,
    private val connectPermissionGranted: () -> Boolean,
    private val adapterProvider: () -> BluetoothAdapter?,
) : PrinterConnection {

    /** Production entry point — resolves the permission and adapter from [context]. */
    constructor(context: Context) : this(
        sdkInt = Build.VERSION.SDK_INT,
        connectPermissionGranted = {
            ContextCompat.checkSelfPermission(
                context.applicationContext,
                Manifest.permission.BLUETOOTH_CONNECT,
            ) == PackageManager.PERMISSION_GRANTED
        },
        adapterProvider = {
            (context.applicationContext.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)
                ?.adapter
        },
    )

    override suspend fun print(macAddress: String, payload: ByteArray): PrintResult =
        withContext(Dispatchers.IO) {
            // Deny-by-default: never touch a protected Bluetooth API before the
            // runtime permission contract is satisfied. This is what keeps the
            // BLUETOOTH_CONNECT path from throwing SecurityException on API 31+.
            if (!hasConnectPermission()) {
                return@withContext PrintResult.Failure("Izin Bluetooth belum diberikan.")
            }

            var socket: BluetoothSocket? = null
            try {
                val adapter = adapterProvider()
                    ?: return@withContext PrintResult.Failure("Perangkat tidak mendukung Bluetooth.")

                if (!adapter.isEnabled) {
                    return@withContext PrintResult.Failure("Bluetooth belum aktif.")
                }

                val device = adapter.getRemoteDevice(macAddress)
                socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
                socket.connect()
                socket.outputStream.use { stream ->
                    stream.write(payload)
                    stream.flush()
                }
                PrintResult.Success
            } catch (e: IllegalArgumentException) {
                PrintResult.Failure("Alamat printer tidak valid.")
            } catch (e: SecurityException) {
                // Defensive: a permission revoked between our check and the call
                // must fail safely, never crash and never hide the defect.
                PrintResult.Failure("Izin Bluetooth ditolak.")
            } catch (e: IOException) {
                PrintResult.Failure("Gagal terhubung ke printer.")
            } finally {
                try {
                    socket?.close()
                } catch (_: IOException) {
                    // best-effort close
                }
            }
        }

    /**
     * BLUETOOTH_CONNECT is only a runtime permission on Android 12+ (API 31).
     * On older devices the legacy install-time BLUETOOTH permission covers it,
     * so the connection is permitted without a runtime grant. BLUETOOTH_SCAN is
     * intentionally never consulted here — this transport does not scan.
     */
    private fun hasConnectPermission(): Boolean {
        if (sdkInt < Build.VERSION_CODES.S) return true
        return connectPermissionGranted()
    }

    private companion object {
        // Standard Serial Port Profile UUID used by ESC/POS thermal printers.
        val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }
}
