package com.aishtech.poslite.feature.printer

import com.aishtech.poslite.data.remote.dto.ReceiptDto

/**
 * UIX-8C-06 — the narrow print seam the [PrinterCoordinator] depends on. Keeping
 * the coordinator behind this interface makes the print concurrency guard and the
 * receipt ViewModel unit-testable on the JVM with a hand fake, without a Context
 * (the real [PrinterRepository] needs SharedPreferences). It never widens
 * responsibility beyond streaming a backend-approved receipt.
 */
interface ReceiptPrinter {
    suspend fun printReceipt(receipt: ReceiptDto): PrintOutcome
}
