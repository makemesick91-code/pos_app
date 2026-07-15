package com.aishtech.poslite.feature.printer

import com.aishtech.poslite.data.remote.dto.ReceiptDto

/**
 * Orchestrates receipt printing (Sprint 6 foundation, UIX-8C-06 typed): enforce
 * print eligibility, format the backend-approved receipt to ESC/POS, and stream
 * it to the configured printer transport.
 *
 * Print eligibility is decided by the backend (`receipt.printable`); this
 * repository refuses to print anything the backend did not approve, so a
 * pending/unpaid/cancelled sale can never produce a final printout. Printing is a
 * presentation operation only: it never creates, mutates, rolls back, or
 * duplicates a sale/payment/sync/inventory record (UIX8C-R191/R192). Every path
 * returns a typed [PrintOutcome].
 */
class PrinterRepository(
    private val connection: PrinterConnection,
    private val settingsStore: PrinterSettingsStore,
    private val formatter: EscPosReceiptFormatter = EscPosReceiptFormatter(),
) : ReceiptPrinter {

    override suspend fun printReceipt(receipt: ReceiptDto): PrintOutcome {
        if (!receipt.printable) {
            return PrintOutcome.Failed(
                reason = PrinterFailure.NOT_PRINTABLE,
                message = receipt.printBlockReason ?: "Struk final belum dapat dicetak.",
                retryable = false,
            )
        }

        val settings = settingsStore.load()
        val mac = settings.printerMacAddress
        if (mac.isNullOrBlank()) {
            return PrintOutcome.Failed(
                reason = PrinterFailure.DEVICE_NOT_CONFIGURED,
                message = "Printer belum dikonfigurasi.",
                retryable = false,
            )
        }

        val payload = formatter.format(
            receipt = receipt,
            paperWidthMm = settings.paperWidthMm,
            autoCut = settings.autoCutEnabled,
        )

        return when (val result = connection.print(mac, payload)) {
            is PrintResult.Success -> PrintOutcome.Printed
            is PrintResult.Failure -> PrintOutcome.Failed(
                reason = result.reason,
                message = result.message,
                retryable = isRetryable(result.reason),
            )
        }
    }

    /** Text preview of the receipt (no device needed) for on-screen display. */
    fun preview(receipt: ReceiptDto): String {
        val cols = if (settingsStore.load().paperWidthMm >= 80) 48 else 32
        return formatter.buildReceiptText(receipt, cols)
    }

    private fun isRetryable(reason: PrinterFailure): Boolean = when (reason) {
        // A configuration/eligibility problem is not fixed by retrying the print.
        PrinterFailure.NOT_PRINTABLE,
        PrinterFailure.DEVICE_NOT_CONFIGURED,
        PrinterFailure.UNSUPPORTED,
        -> false
        // Everything else may succeed on a safe reprint once the operator acts
        // (grant permission, enable Bluetooth, retry a transient failure).
        else -> true
    }
}
