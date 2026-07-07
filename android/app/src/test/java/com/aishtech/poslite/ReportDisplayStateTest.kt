package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryItemDto
import com.aishtech.poslite.data.remote.dto.PaymentSummaryItemDto
import com.aishtech.poslite.feature.reports.ReportDisplay
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * Sprint 9 — pure-JVM tests for the reports display mapping. Report values come
 * from the backend; the mapping only formats them and must degrade gracefully
 * for null/empty/malformed values (never crash the summary screen).
 */
class ReportDisplayStateTest {

    @Test
    fun `money formats decimal strings and falls back safely`() {
        assertEquals("Rp 100.000", ReportDisplay.money("100000.00"))
        assertEquals("Rp 0", ReportDisplay.money(null))
        assertEquals("Rp 0", ReportDisplay.money(""))
        assertEquals("Rp 0", ReportDisplay.money("not-a-number"))
    }

    @Test
    fun `text falls back to dash for null or blank`() {
        assertEquals("-", ReportDisplay.text(null))
        assertEquals("-", ReportDisplay.text(""))
        assertEquals("12.00", ReportDisplay.text("12.00"))
    }

    @Test
    fun `paid total for method only sums paid rows of that method`() {
        val items = listOf(
            PaymentSummaryItemDto("CASH", "PAID", 2, "70000.00"),
            PaymentSummaryItemDto("QRIS", "PAID", 1, "30000.00"),
            PaymentSummaryItemDto("QRIS", "PENDING", 1, "99000.00"),
        )

        assertEquals("70000.00", ReportDisplay.paidTotalForMethod(items, "CASH"))
        // Pending QRIS is never mixed into the paid QRIS total.
        assertEquals("30000.00", ReportDisplay.paidTotalForMethod(items, "QRIS"))
        assertEquals("0.00", ReportDisplay.paidTotalForMethod(emptyList(), "CASH"))
        assertEquals("0.00", ReportDisplay.paidTotalForMethod(null, "CASH"))
    }

    @Test
    fun `sale out qty is read from inventory summary with safe fallback`() {
        val items = listOf(
            InventoryMovementSummaryItemDto("SALE_OUT", 10, "25.00", "-25.00"),
            InventoryMovementSummaryItemDto("ADJUSTMENT_IN", 1, "10.00", "10.00"),
        )

        assertEquals("25.00", ReportDisplay.saleOutQty(items))
        assertEquals("0.00", ReportDisplay.saleOutQty(emptyList()))
        assertEquals("0.00", ReportDisplay.saleOutQty(null))
    }

    @Test
    fun `closing message distinguishes fresh close from duplicate replay`() {
        assertEquals("Hari ini berhasil ditutup.", ReportDisplay.closingMessage(false))
        assertEquals("Hari ini sudah ditutup sebelumnya.", ReportDisplay.closingMessage(true))
    }
}
