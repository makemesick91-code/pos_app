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
 * manually-entered / paired MAC in printer settings. All work is off the main
 * thread and every failure is surfaced as [PrintResult.Failure] — never a crash.
 */
class BluetoothPrinterConnection(context: Context) : PrinterConnection {

    private val appContext = context.applicationContext

    override suspend fun print(macAddress: String, payload: ByteArray): PrintResult =
        withContext(Dispatchers.IO) {
            if (!hasConnectPermission()) {
                return@withContext PrintResult.Failure("Izin Bluetooth belum diberikan.")
            }

            val adapter = bluetoothAdapter()
                ?: return@withContext PrintResult.Failure("Perangkat tidak mendukung Bluetooth.")

            if (!adapter.isEnabled) {
                return@withContext PrintResult.Failure("Bluetooth belum aktif.")
            }

            var socket: BluetoothSocket? = null
            try {
                val device = adapter.getRemoteDevice(macAddress)
                socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
                adapter.cancelDiscovery()
                socket.connect()
                socket.outputStream.use { stream ->
                    stream.write(payload)
                    stream.flush()
                }
                PrintResult.Success
            } catch (e: IllegalArgumentException) {
                PrintResult.Failure("Alamat printer tidak valid.")
            } catch (e: SecurityException) {
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

    private fun bluetoothAdapter(): BluetoothAdapter? =
        (appContext.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter

    /**
     * BLUETOOTH_CONNECT is only a runtime permission on Android 12+ (API 31).
     * On older devices the legacy install-time BLUETOOTH permission covers it.
     */
    private fun hasConnectPermission(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return true
        return ContextCompat.checkSelfPermission(
            appContext,
            Manifest.permission.BLUETOOTH_CONNECT,
        ) == PackageManager.PERMISSION_GRANTED
    }

    private companion object {
        // Standard Serial Port Profile UUID used by ESC/POS thermal printers.
        val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }
}
