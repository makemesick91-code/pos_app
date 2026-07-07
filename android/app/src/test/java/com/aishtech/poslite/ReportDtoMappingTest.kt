package com.aishtech.poslite

import com.aishtech.poslite.data.remote.dto.DailySalesReportResponseDto
import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryResponseDto
import com.aishtech.poslite.data.remote.dto.PaymentSummaryResponseDto
import com.google.gson.Gson
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * Sprint 9 — verifies the report DTOs map the backend JSON (snake_case wire
 * names) onto the Kotlin fields the UI reads. Report totals are authoritative on
 * the backend; the app only displays them.
 */
class ReportDtoMappingTest {

    private val gson = Gson()

    @Test
    fun `daily sales dto maps values correctly`() {
        val json = """
            {
              "data": {
                "business_date": "2026-07-07",
                "store_id": 1,
                "sales_count": 10,
                "cancelled_sales_count": 1,
                "gross_total": "100000.00",
                "discount_total": "0.00",
                "tax_total": "0.00",
                "grand_total": "100000.00",
                "paid_total": "100000.00",
                "change_total": "5000.00",
                "average_sale": "10000.00",
                "cash_sales_count": 7,
                "qris_sales_count": 3
              },
              "meta": { "foundation": "POS_ANDROID_SAAS_FOUNDATION" }
            }
        """.trimIndent()

        val dto = gson.fromJson(json, DailySalesReportResponseDto::class.java)
        val data = dto.data!!

        assertEquals("2026-07-07", data.businessDate)
        assertEquals(10, data.salesCount)
        assertEquals(1, data.cancelledSalesCount)
        assertEquals("100000.00", data.grandTotal)
        assertEquals(7, data.cashSalesCount)
        assertEquals(3, data.qrisSalesCount)
    }

    @Test
    fun `payment summary dto maps cash and qris values`() {
        val json = """
            {
              "data": [
                { "method": "CASH", "status": "PAID", "count": 7, "amount_total": "70000.00" },
                { "method": "QRIS", "status": "PAID", "count": 3, "amount_total": "30000.00" }
              ]
            }
        """.trimIndent()

        val dto = gson.fromJson(json, PaymentSummaryResponseDto::class.java)

        assertEquals(2, dto.data.size)
        val cash = dto.data.first { it.method == "CASH" }
        val qris = dto.data.first { it.method == "QRIS" }
        assertEquals("PAID", cash.status)
        assertEquals(7, cash.count)
        assertEquals("70000.00", cash.amountTotal)
        assertEquals("30000.00", qris.amountTotal)
    }

    @Test
    fun `inventory movement summary dto maps signed qty values`() {
        val json = """
            {
              "data": [
                { "movement_type": "SALE_OUT", "movement_count": 10, "qty_total": "25.00", "signed_qty_total": "-25.00" },
                { "movement_type": "ADJUSTMENT_IN", "movement_count": 1, "qty_total": "10.00", "signed_qty_total": "10.00" }
              ]
            }
        """.trimIndent()

        val dto = gson.fromJson(json, InventoryMovementSummaryResponseDto::class.java)

        val saleOut = dto.data.first { it.movementType == "SALE_OUT" }
        assertEquals(10, saleOut.movementCount)
        assertEquals("25.00", saleOut.qtyTotal)
        assertEquals("-25.00", saleOut.signedQtyTotal)
    }
}
