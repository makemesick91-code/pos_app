package com.aishtech.poslite

import androidx.arch.core.executor.testing.InstantTaskExecutorRule
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.remote.dto.ReceiptTotalsDto
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.printer.PrintOutcome
import com.aishtech.poslite.feature.printer.PrinterCoordinator
import com.aishtech.poslite.feature.printer.ReceiptPrinter
import com.aishtech.poslite.feature.receipt.LocalReceiptSource
import com.aishtech.poslite.feature.receipt.ReceiptTransactionState
import com.aishtech.poslite.feature.receipt.ReceiptViewModel
import com.aishtech.poslite.feature.receipt.ServerReceiptSource
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test

/**
 * UIX-8C-06 — the receipt ViewModel binds to exactly one logical transaction,
 * restores durable truth from Room, rejects a projection whose identity does not
 * match the request (stale-result guard, UIX8C-R173/R190), and fires print
 * feedback exactly once.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ReceiptViewModelTest {

    @get:Rule val rule = InstantTaskExecutorRule()
    private val dispatcher = StandardTestDispatcher()

    @Before fun setUp() = Dispatchers.setMain(dispatcher)
    @After fun tearDown() = Dispatchers.resetMain()

    private class FakeServer(private val result: ResultState<ReceiptDto>) : ServerReceiptSource {
        override suspend fun getReceipt(saleId: Long): ResultState<ReceiptDto> = result
    }

    private class FakeLocal(
        private val byRef: Map<String, OfflineSaleRepository.LocalSaleWithItems> = emptyMap(),
        private val byId: Map<Long, OfflineSaleRepository.LocalSaleWithItems> = emptyMap(),
    ) : LocalReceiptSource {
        override suspend fun findSaleWithItems(localId: Long) = byId[localId]
        override suspend fun findSaleWithItemsByReference(clientReference: String) = byRef[clientReference]
    }

    private class FakePrinter(private val outcome: PrintOutcome) : ReceiptPrinter {
        var calls = 0
        override suspend fun printReceipt(receipt: ReceiptDto): PrintOutcome {
            calls++
            return outcome
        }
    }

    private fun printableDto(saleId: Long = 900) = ReceiptDto(
        saleId = saleId, invoiceNumber = "INV-$saleId", receiptStatus = "FINAL", printable = true,
        printBlockReason = null, store = null, cashier = null, saleDate = "d", paymentStatus = "PAID",
        items = emptyList(), payments = emptyList(),
        totals = ReceiptTotalsDto("20000.00", "0.00", "0.00", "20000.00", "20000.00", "0.00"),
        footer = null,
    )

    private fun sale(cref: String, status: String, serverSaleId: Long? = null, localId: Long = 1) =
        OfflineSaleRepository.LocalSaleWithItems(
            LocalOfflineSaleEntity(
                localId = localId, clientReference = cref, storeId = 1, saleDate = "d",
                subtotal = 20000.0, discountTotal = 0.0, taxTotal = 0.0, grandTotal = 20000.0,
                paidAmount = 20000.0, changeAmount = 0.0, syncStatus = status, syncAttemptCount = 0,
                serverSaleId = serverSaleId, serverInvoiceNumber = null, createdAt = 1, updatedAt = 1,
            ),
            listOf(
                LocalOfflineSaleItemEntity(
                    localId = 1, offlineSaleLocalId = localId, productId = 1, productName = "Kopi",
                    qty = 2, unitPrice = 10000.0, discount = 0.0, subtotal = 20000.0,
                ),
            ),
        )

    private fun vm(
        server: ServerReceiptSource = FakeServer(ResultState.Success(printableDto())),
        local: LocalReceiptSource = FakeLocal(),
        printer: ReceiptPrinter = FakePrinter(PrintOutcome.Printed),
    ) = ReceiptViewModel(server, local, PrinterCoordinator(printer))

    @Test fun loadServerSale_ready_online_and_printable() = runTest {
        val vm = vm()
        vm.loadServerSale(900, clientReference = "cref-1")
        advanceUntilIdle()
        val state = vm.state.value as ReceiptViewModel.UiState.Ready
        assertEquals(ReceiptTransactionState.ONLINE_SUCCESS, state.projection.state)
        assertTrue("a backend-approved receipt is printable", state.printable)
    }

    @Test fun loadLocalPending_ready_offline_and_notPrintable() = runTest {
        val vm = vm(local = FakeLocal(byRef = mapOf("cref-1" to sale("cref-1", OfflineSyncStatus.PENDING))))
        vm.loadLocalByReference("cref-1")
        advanceUntilIdle()
        val state = vm.state.value as ReceiptViewModel.UiState.Ready
        assertEquals(ReceiptTransactionState.OFFLINE_PENDING, state.projection.state)
        assertFalse("a pending offline draft is not printed until synced", state.printable)
    }

    @Test fun loadLocalSynced_restoresFromRoom_andEnablesReprint() = runTest {
        val vm = vm(
            server = FakeServer(ResultState.Success(printableDto(saleId = 5))),
            local = FakeLocal(byId = mapOf(9L to sale("cref-1", OfflineSyncStatus.SYNCED, serverSaleId = 5, localId = 9))),
        )
        vm.loadLocalById(9)
        advanceUntilIdle()
        val state = vm.state.value as ReceiptViewModel.UiState.Ready
        assertEquals(ReceiptTransactionState.SYNCED, state.projection.state)
        assertTrue("a synced local row can be reprinted from its backend receipt", state.printable)
    }

    @Test fun staleData_identityMismatch_isNotPublished() = runTest {
        // The store returns a sale whose clientReference does not match the request.
        val vm = vm(local = FakeLocal(byRef = mapOf("requested" to sale("OTHER", OfflineSyncStatus.PENDING))))
        vm.loadLocalByReference("requested")
        advanceUntilIdle()
        // The mismatched projection must NOT surface as Ready (stale-result guard).
        assertFalse(vm.state.value is ReceiptViewModel.UiState.Ready)
    }

    @Test fun print_firesEventExactlyOnce() = runTest {
        val vm = vm()
        vm.loadServerSale(900)
        advanceUntilIdle()
        vm.print()
        advanceUntilIdle()
        val event = vm.printEvent.value!!
        assertEquals(PrintOutcome.Printed, event.getContentIfNotHandled())
        assertNull("a one-shot print event must not replay", event.getContentIfNotHandled())
    }

    @Test fun print_onPendingReceipt_doesNotPrint() = runTest {
        val printer = FakePrinter(PrintOutcome.Printed)
        val vm = vm(
            local = FakeLocal(byRef = mapOf("cref-1" to sale("cref-1", OfflineSyncStatus.PENDING))),
            printer = printer,
        )
        vm.loadLocalByReference("cref-1")
        advanceUntilIdle()
        vm.print()
        advanceUntilIdle()
        assertEquals("printing a non-printable pending draft is a no-op", 0, printer.calls)
    }
}
