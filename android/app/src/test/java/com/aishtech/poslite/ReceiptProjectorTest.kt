package com.aishtech.poslite

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.remote.dto.ReceiptItemDto
import com.aishtech.poslite.data.remote.dto.ReceiptPaymentDto
import com.aishtech.poslite.data.remote.dto.ReceiptStoreDto
import com.aishtech.poslite.data.remote.dto.ReceiptTotalsDto
import com.aishtech.poslite.feature.receipt.ReceiptProjector
import com.aishtech.poslite.feature.receipt.ReceiptTransactionState
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-06 — receipt projection binding + parity (UIX8C-R172/R177/R179).
 * Proves that the receipt exactly mirrors its canonical transaction source and
 * that a durable local save is NEVER projected as synced.
 */
class ReceiptProjectorTest {

    private fun sale(
        status: String = OfflineSyncStatus.PENDING,
        serverSaleId: Long? = null,
        invoice: String? = null,
    ) = LocalOfflineSaleEntity(
        localId = 42,
        clientReference = "cref-1",
        storeId = 7,
        saleDate = "2026-07-15T10:00:00Z",
        subtotal = 30000.0,
        discountTotal = 0.0,
        taxTotal = 0.0,
        grandTotal = 30000.0,
        paidAmount = 50000.0,
        changeAmount = 20000.0,
        syncStatus = status,
        syncAttemptCount = 0,
        lastSyncError = null,
        serverSaleId = serverSaleId,
        serverInvoiceNumber = invoice,
        createdAt = 1000,
        updatedAt = 1000,
    )

    private fun items() = listOf(
        LocalOfflineSaleItemEntity(
            localId = 1, offlineSaleLocalId = 42, productId = 100, productName = "Kopi",
            qty = 2, unitPrice = 10000.0, discount = 0.0, subtotal = 20000.0,
        ),
        LocalOfflineSaleItemEntity(
            localId = 2, offlineSaleLocalId = 42, productId = 101, productName = "Teh",
            qty = 1, unitPrice = 10000.0, discount = 0.0, subtotal = 10000.0,
        ),
    )

    @Test fun localPending_projectsOfflinePending_neverSynced() {
        val p = ReceiptProjector.fromLocalSale(sale(OfflineSyncStatus.PENDING), items())
        assertEquals(ReceiptTransactionState.OFFLINE_PENDING, p.state)
        assertFalse("a durable pending save must not be server-acknowledged", p.state.isServerAcknowledged)
        assertTrue(p.isOffline)
    }

    @Test fun localSynced_projectsSynced() {
        val p = ReceiptProjector.fromLocalSale(
            sale(OfflineSyncStatus.SYNCED, serverSaleId = 900, invoice = "INV-9"), items(),
        )
        assertEquals(ReceiptTransactionState.SYNCED, p.state)
        assertTrue(p.state.isServerAcknowledged)
        assertEquals("INV-9", p.reference)
    }

    @Test fun unknownStatus_failsSafeToOfflinePending_neverSynced() {
        val p = ReceiptProjector.fromLocalSale(sale("WEIRD"), items())
        assertEquals(ReceiptTransactionState.OFFLINE_PENDING, p.state)
        assertFalse(p.state.isServerAcknowledged)
    }

    @Test fun localProjection_bindsIdentity() {
        val p = ReceiptProjector.fromLocalSale(sale(serverSaleId = 5), items())
        assertEquals("cref-1", p.identity.clientReference)
        assertEquals(5L, p.identity.serverSaleId)
        assertEquals(42L, p.identity.localId)
    }

    @Test fun localProjection_moneyAndItemsAreExact() {
        val p = ReceiptProjector.fromLocalSale(sale(), items())
        assertEquals(2, p.itemCount)
        assertEquals("Kopi", p.lines[0].productName)
        assertEquals(2, p.lines[0].quantity)
        assertEquals(10000L, p.lines[0].unitPrice)
        assertEquals(20000L, p.lines[0].lineTotal)
        assertEquals(30000L, p.subtotal)
        assertEquals(30000L, p.grandTotal)
        assertEquals(50000L, p.tender)
        assertEquals(20000L, p.change)
        assertEquals("CASH", p.paymentMethod)
    }

    private fun serverReceipt() = ReceiptDto(
        saleId = 900,
        invoiceNumber = "INV-900",
        receiptStatus = "FINAL",
        printable = true,
        printBlockReason = null,
        store = ReceiptStoreDto(name = "Toko A", code = "A1", address = "Jl. 1"),
        cashier = com.aishtech.poslite.data.remote.dto.ReceiptCashierDto(name = "Budi"),
        saleDate = "2026-07-15T10:00:00Z",
        paymentStatus = "PAID",
        items = listOf(
            ReceiptItemDto(
                productName = "Kopi", productSku = "K1", qty = "2", unit = "pcs",
                unitPrice = "10000.00", discount = "0.00", subtotal = "20000.00",
            ),
        ),
        payments = listOf(
            ReceiptPaymentDto(method = "CASH", provider = null, status = "PAID", amount = "50000.00", paidAt = null),
        ),
        totals = ReceiptTotalsDto(
            subtotal = "20000.00", discountTotal = "0.00", taxTotal = "0.00",
            grandTotal = "20000.00", paidTotal = "50000.00", changeTotal = "30000.00",
        ),
        footer = "Terima kasih",
    )

    @Test fun serverReceipt_parsesDecimalStringsToExactWholeRupiah() {
        val p = ReceiptProjector.fromServerReceipt(serverReceipt(), clientReference = "cref-1")
        // "20000.00" must read as 20000, NOT 2000000 (the '.' is a decimal, not grouping).
        assertEquals(20000L, p.grandTotal)
        assertEquals(20000L, p.subtotal)
        assertEquals(50000L, p.tender)
        assertEquals(30000L, p.change)
        assertEquals(1, p.itemCount)
        assertEquals(2, p.lines[0].quantity)
        assertEquals(10000L, p.lines[0].unitPrice)
        assertEquals(20000L, p.lines[0].lineTotal)
    }

    @Test fun serverReceipt_onlineIsSuccessState_syncedFlagMapsToSynced() {
        assertEquals(
            ReceiptTransactionState.ONLINE_SUCCESS,
            ReceiptProjector.fromServerReceipt(serverReceipt()).state,
        )
        assertEquals(
            ReceiptTransactionState.SYNCED,
            ReceiptProjector.fromServerReceipt(serverReceipt(), synced = true).state,
        )
    }

    @Test fun serverReceipt_bindsServerIdAndCarriesClientReference() {
        val p = ReceiptProjector.fromServerReceipt(serverReceipt(), clientReference = "cref-1")
        assertEquals(900L, p.identity.serverSaleId)
        assertEquals("cref-1", p.identity.clientReference)
        assertNull(p.identity.localId)
        assertEquals("Toko A", p.businessName)
        assertEquals("Budi", p.cashierName)
    }
}
