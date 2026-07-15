package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.feature.printer.PrintOutcome
import com.aishtech.poslite.feature.printer.PrinterCoordinator
import com.aishtech.poslite.feature.printer.PrinterFailure
import com.aishtech.poslite.feature.printer.ReceiptPrinter
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.launch
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.Assert.assertEquals
import org.junit.Assert.assertSame
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-06 — the print coordinator guarantees at most one active print job
 * (UIX8C-R198), passes typed failures through unchanged (UIX8C-R197), reuses the
 * same immutable receipt for a reprint (no new transaction — UIX8C-R193), and
 * never references any sale/payment/sync type (non-financial by construction —
 * UIX8C-R191/R192).
 */
@OptIn(ExperimentalCoroutinesApi::class)
class PrinterCoordinatorTest {

    private fun receipt(id: Long = 1) = ReceiptDto(
        saleId = id, invoiceNumber = "INV-$id", receiptStatus = "FINAL", printable = true,
        printBlockReason = null, store = null, cashier = null, saleDate = null,
        paymentStatus = "PAID", items = emptyList(), payments = emptyList(),
        totals = null, footer = null,
    )

    private class FakePrinter(private val outcome: PrintOutcome) : ReceiptPrinter {
        val received = mutableListOf<ReceiptDto>()
        val gate = CompletableDeferred<Unit>()
        var holdOpen = false
        override suspend fun printReceipt(receipt: ReceiptDto): PrintOutcome {
            received.add(receipt)
            if (holdOpen) gate.await()
            return outcome
        }
    }

    @Test fun print_delegatesToPrinter() = runTest {
        val fake = FakePrinter(PrintOutcome.Printed)
        val r = receipt()
        val outcome = PrinterCoordinator(fake).print(r)
        assertEquals(PrintOutcome.Printed, outcome)
        assertEquals(1, fake.received.size)
        assertSame(r, fake.received.single())
    }

    @Test fun typedFailure_passesThroughUnchanged() = runTest {
        val failure = PrintOutcome.Failed(PrinterFailure.TIMEOUT, "Printer tidak merespons.", retryable = true)
        val outcome = PrinterCoordinator(FakePrinter(failure)).print(receipt())
        assertEquals(failure, outcome)
    }

    @Test fun secondConcurrentPrint_isRejectedAsAlreadyPrinting() = runTest {
        val fake = FakePrinter(PrintOutcome.Printed).apply { holdOpen = true }
        val coordinator = PrinterCoordinator(fake)

        val first = launch { coordinator.print(receipt()) }
        advanceUntilIdle() // first job is now in flight, holding the gate

        val second = coordinator.print(receipt())
        assertEquals(PrintOutcome.AlreadyPrinting, second)
        assertEquals("the second tap must not open a second print", 1, fake.received.size)

        fake.gate.complete(Unit)
        first.join()
    }

    @Test fun reprint_reusesSameReceipt_andCreatesNoSecondLogicalPrintPath() = runTest {
        val fake = FakePrinter(PrintOutcome.Printed)
        val coordinator = PrinterCoordinator(fake)
        val r = receipt(7)

        assertEquals(PrintOutcome.Printed, coordinator.print(r))   // print
        assertEquals(PrintOutcome.Printed, coordinator.print(r))   // reprint

        assertEquals(2, fake.received.size)
        assertTrue("reprint reuses the exact same immutable receipt", fake.received.all { it === r })
    }
}
