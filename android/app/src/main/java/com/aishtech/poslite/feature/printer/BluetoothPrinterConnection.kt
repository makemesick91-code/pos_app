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
import kotlinx.coroutines.withTimeoutOrNull
import java.io.IOException
import java.util.UUID

/**
 * Android-native, lightweight Bluetooth SPP printer transport (Sprint 6
 * foundation, UIX-8C-06 hardened). It connects to an already-paired thermal
 * printer by MAC address over the standard Serial Port Profile UUID and streams
 * the ESC/POS bytes.
 *
 * Scope guard: no discovery, no vendor SDK. The printer is selected from a
 * manually-entered / paired MAC in printer settings.
 *
 * Permission contract (see .claude/rules/58-android-bluetooth-permission-foundation.md
 * and UIX8C-R194..R196): because this transport never starts device discovery, it
 * MUST NOT call the scan/discovery APIs (`startDiscovery` / `cancelDiscovery`) and
 * therefore never requires the dangerous `BLUETOOTH_SCAN` permission, and never
 * needs a location permission to reach an already-paired device. Its only
 * protected calls (`createRfcommSocketToServiceRecord`, `connect`) need
 * `BLUETOOTH_CONNECT`, which is a runtime permission on Android 12+ (API 31) and
 * covered by the legacy install-time `BLUETOOTH` permission on API 30 and below.
 * The manifest declares exactly those permissions and deliberately omits
 * `BLUETOOTH_SCAN`.
 *
 * Every outcome is a typed [PrintResult] (UIX8C-R197): a denied permission, a
 * disabled adapter, an invalid address, a connect vs. write failure, a bounded
 * timeout, an interruption, and any unexpected error are each surfaced as a
 * distinct [PrinterFailure] with an actionable, secret-free message — never a
 * crash and never a fabricated success. Connect and write run under a bounded
 * timeout so a hung printer can never block the IO coroutine indefinitely
 * (UIX8C-R198/R200).
 *
 * The primary constructor takes Context-free capability seams so the API-level
 * permission contract is unit-testable on the JVM without an Android device; the
 * public [constructor] wires the real Android probes.
 */
class BluetoothPrinterConnection internal constructor(
    private val sdkInt: Int,
    private val connectPermissionGranted: () -> Boolean,
    private val adapterProvider: () -> BluetoothAdapter?,
    private val timeoutMs: Long = DEFAULT_TIMEOUT_MS,
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
                return@withContext PrintResult.Failure(
                    PrinterFailure.PERMISSION_REQUIRED,
                    "Izin Bluetooth belum diberikan.",
                )
            }

            // Bounded: a hung printer must never block the IO coroutine forever
            // (UIX8C-R200). withTimeoutOrNull returns null on expiry without
            // throwing into our catch, so coroutine cancellation stays clean.
            val result = withTimeoutOrNull(timeoutMs) { attemptPrint(macAddress, payload) }
            result ?: PrintResult.Failure(
                PrinterFailure.TIMEOUT,
                "Printer tidak merespons. Coba cetak ulang.",
            )
        }

    private fun attemptPrint(macAddress: String, payload: ByteArray): PrintResult {
        var socket: BluetoothSocket? = null
        var connected = false
        return try {
            val adapter = adapterProvider()
                ?: return PrintResult.Failure(
                    PrinterFailure.UNSUPPORTED,
                    "Perangkat tidak mendukung Bluetooth.",
                )

            if (!adapter.isEnabled) {
                return PrintResult.Failure(
                    PrinterFailure.ADAPTER_DISABLED,
                    "Bluetooth belum aktif.",
                )
            }

            val device = adapter.getRemoteDevice(macAddress)
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            socket.connect()
            connected = true
            socket.outputStream.use { stream ->
                stream.write(payload)
                stream.flush()
            }
            PrintResult.Success
        } catch (e: IllegalArgumentException) {
            PrintResult.Failure(
                PrinterFailure.DEVICE_UNAVAILABLE,
                "Alamat printer tidak valid.",
            )
        } catch (e: SecurityException) {
            // Defensive: a permission revoked between our check and the call must
            // fail safely, never crash and never hide the defect.
            PrintResult.Failure(
                PrinterFailure.PERMISSION_DENIED,
                "Izin Bluetooth ditolak.",
            )
        } catch (e: IOException) {
            // Distinguish a failed connection from a failed write so the operator
            // gets an accurate, actionable message (UIX8C-R197).
            if (connected) {
                PrintResult.Failure(
                    PrinterFailure.WRITE_FAILED,
                    "Gagal mengirim data ke printer.",
                )
            } else {
                PrintResult.Failure(
                    PrinterFailure.CONNECTION_FAILED,
                    "Gagal terhubung ke printer.",
                )
            }
        } catch (e: Exception) {
            // Catch-all: any other unexpected error is surfaced safely as a typed
            // failure — the transport never crashes the app (UIX8C-R197).
            PrintResult.Failure(
                PrinterFailure.UNKNOWN_SAFE_FAILURE,
                "Gagal mencetak struk. Coba cetak ulang.",
            )
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

        // Bounded connect+write budget so a hung printer cannot starve the IO pool.
        const val DEFAULT_TIMEOUT_MS = 8_000L
    }
}
