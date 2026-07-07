package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.ReceiptCashierDto
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.remote.dto.ReceiptItemDto
import com.aishtech.poslite.data.remote.dto.ReceiptPaymentDto
import com.aishtech.poslite.data.remote.dto.ReceiptStoreDto
import com.aishtech.poslite.data.remote.dto.ReceiptTotalsDto
import com.aishtech.poslite.feature.printer.EscPosReceiptFormatter
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Pure-JVM tests for the Sprint 6 ESC/POS formatter. No Bluetooth device or
 * Android runtime required — the formatter is deliberately pure Kotlin.
 */
class EscPosReceiptFormatterTest {

    private val formatter = EscPosReceiptFormatter()

    private fun sampleReceipt() = ReceiptDto(
        saleId = 1,
        invoiceNumber = "POS-A1-20260707-000001",
        receiptStatus = "FINAL",
        printable = true,
        printBlockReason = null,
        store = ReceiptStoreDto(name = "Store A1", code = "A1", address = "Jl. Contoh No. 1"),
        cashier = ReceiptCashierDto(name = "Kasir A"),
        saleDate = "2026-07-07T00:00:00+00:00",
        paymentStatus = "PAID",
        items = listOf(
            ReceiptItemDto(
                productName = "Produk Snapshot",
                productSku = "SKU-A-001",
                qty = "2.00",
                unit = "pcs",
                unitPrice = "10000.00",
                discount = "0.00",
                subtotal = "20000.00",
            ),
        ),
        payments = listOf(
            ReceiptPaymentDto(
                method = "CASH",
                provider = "MANUAL",
                status = "PAID",
                amount = "20000.00",
                paidAt = "2026-07-07T00:00:00+00:00",
            ),
        ),
        totals = ReceiptTotalsDto(
            subtotal = "20000.00",
            discountTotal = "0.00",
            taxTotal = "0.00",
            grandTotal = "20000.00",
            paidTotal = "20000.00",
            changeTotal = "0.00",
        ),
        footer = "Terima kasih",
    )

    @Test
    fun `text body includes store, invoice, snapshot item, total, payment and footer`() {
        val text = formatter.buildReceiptText(sampleReceipt())

        assertTrue(text.contains("Store A1"))
        assertTrue(text.contains("POS-A1-20260707-000001"))
        assertTrue(text.contains("Produk Snapshot"))
        assertTrue(text.contains("20000.00"))
        assertTrue(text.contains("CASH"))
        assertTrue(text.contains("Terima kasih"))
        assertTrue(text.contains("Kasir A"))
    }

    @Test
    fun `text body never leaks a raw gateway payload`() {
        val text = formatter.buildReceiptText(sampleReceipt())

        assertFalse(text.contains("raw_response"))
        assertFalse(text.contains("secret"))
    }

    @Test
    fun `format emits an ESC POS init header and a cut when auto-cut is on`() {
        val bytes = formatter.format(sampleReceipt(), paperWidthMm = 58, autoCut = true)

        // ESC @ initialize sequence.
        assertEquals(0x1B.toByte(), bytes[0])
        assertEquals(0x40.toByte(), bytes[1])

        // GS V 66 0 partial-cut sequence at the tail.
        val tail = bytes.copyOfRange(bytes.size - 4, bytes.size)
        assertEquals(0x1D.toByte(), tail[0])
        assertEquals(0x56.toByte(), tail[1])
        assertEquals(0x42.toByte(), tail[2])
        assertEquals(0x00.toByte(), tail[3])
    }

    @Test
    fun `format without auto-cut omits the cut sequence`() {
        val bytes = formatter.format(sampleReceipt(), paperWidthMm = 80, autoCut = false)
        val tail = bytes.copyOfRange(bytes.size - 4, bytes.size)

        val isCut = tail[0] == 0x1D.toByte() && tail[1] == 0x56.toByte()
        assertFalse(isCut)
    }
}
