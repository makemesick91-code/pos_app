package com.aishtech.poslite.feature.printer

import com.aishtech.poslite.data.remote.dto.ReceiptDto
import java.util.concurrent.atomic.AtomicBoolean

/**
 * UIX-8C-06 — the single canonical entry point for printing/reprinting a
 * receipt. It exists to guarantee two invariants the raw [PrinterRepository]
 * cannot on its own:
 *
 *  1. At most one print job is active at a time for this coordinator: a rapid
 *     double-tap or a reprint while a job is in flight is ignored, returning
 *     [PrintOutcome.AlreadyPrinting] instead of opening a second connection
 *     (UIX8C-R198). The retry is bounded — the coordinator itself never loops.
 *  2. Printing never touches transaction authority. This class has no reference
 *     to any sale/payment/offline/sync/inventory repository; it only formats and
 *     streams a backend-approved receipt (UIX8C-R191/R192/R193). A reprint reuses
 *     the same immutable receipt and creates no new transaction, clientReference,
 *     payment, sale item, or inventory movement.
 *
 * Pure orchestration over an injected [PrinterRepository]; unit-testable on the JVM.
 */
class PrinterCoordinator(private val printer: ReceiptPrinter) {

    private val active = AtomicBoolean(false)

    /** True while a print job is in flight (for UI progress). */
    val isPrinting: Boolean get() = active.get()

    /**
     * Print the given backend-approved [receipt]. Reprint calls the same method
     * with the same persisted receipt — there is no separate "reprint" path that
     * could re-ring a transaction (UIX8C-R193).
     */
    suspend fun print(receipt: ReceiptDto): PrintOutcome {
        if (!active.compareAndSet(false, true)) {
            return PrintOutcome.AlreadyPrinting
        }
        return try {
            printer.printReceipt(receipt)
        } finally {
            active.set(false)
        }
    }
}
