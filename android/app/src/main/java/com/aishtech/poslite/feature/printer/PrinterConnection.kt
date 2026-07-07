package com.aishtech.poslite.feature.printer

/**
 * Transport-agnostic printer connection (Sprint 6 foundation). Bluetooth is the
 * first implementation; the abstraction keeps ESC/POS payload delivery decoupled
 * from any specific transport and from any heavy vendor SDK.
 */
interface PrinterConnection {
    /** Send a raw ESC/POS byte payload to the printer identified by [macAddress]. */
    suspend fun print(macAddress: String, payload: ByteArray): PrintResult
}

sealed class PrintResult {
    data object Success : PrintResult()
    data class Failure(val message: String) : PrintResult()
}
