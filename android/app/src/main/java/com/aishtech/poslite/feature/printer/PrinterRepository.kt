package com.aishtech.poslite.feature.printer

import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.ReceiptDto

/**
 * Orchestrates receipt printing (Sprint 6 foundation): enforce print eligibility,
 * format the backend-approved receipt to ESC/POS, and stream it to the configured
 * printer transport.
 *
 * Print eligibility is decided by the backend (`receipt.printable`); this
 * repository refuses to print anything the backend did not approve, so a
 * pending/unpaid/cancelled sale can never produce a final printout.
 */
class PrinterRepository(
    private val connection: PrinterConnection,
    private val settingsStore: PrinterSettingsStore,
    private val formatter: EscPosReceiptFormatter = EscPosReceiptFormatter(),
) {

    suspend fun printReceipt(receipt: ReceiptDto): ResultState<Unit> {
        if (!receipt.printable) {
            return ResultState.Error(
                receipt.printBlockReason ?: "Struk final belum dapat dicetak.",
            )
        }

        val settings = settingsStore.load()
        val mac = settings.printerMacAddress
        if (mac.isNullOrBlank()) {
            return ResultState.Error("Printer belum dikonfigurasi.")
        }

        val payload = formatter.format(
            receipt = receipt,
            paperWidthMm = settings.paperWidthMm,
            autoCut = settings.autoCutEnabled,
        )

        return when (val result = connection.print(mac, payload)) {
            is PrintResult.Success -> ResultState.Success(Unit)
            is PrintResult.Failure -> ResultState.Error(result.message)
        }
    }

    /** Text preview of the receipt (no device needed) for on-screen display. */
    fun preview(receipt: ReceiptDto): String {
        val cols = if (settingsStore.load().paperWidthMm >= 80) 48 else 32
        return formatter.buildReceiptText(receipt, cols)
    }
}
