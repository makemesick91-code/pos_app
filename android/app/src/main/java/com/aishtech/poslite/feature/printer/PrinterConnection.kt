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

/**
 * The typed result of a low-level print attempt (UIX8C-R197). A failure carries a
 * distinct [PrinterFailure] reason plus a human-readable, secret-free message, so
 * callers can react precisely (retry, request permission, guide to settings)
 * instead of pattern-matching on a free-text string.
 */
sealed class PrintResult {
    data object Success : PrintResult()
    data class Failure(val reason: PrinterFailure, val message: String) : PrintResult()
}
