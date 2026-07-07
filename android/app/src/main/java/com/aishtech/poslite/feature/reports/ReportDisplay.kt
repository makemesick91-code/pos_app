package com.aishtech.poslite.feature.reports

import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryItemDto
import com.aishtech.poslite.data.remote.dto.PaymentSummaryItemDto
import java.text.NumberFormat
import java.util.Locale

/**
 * Pure-JVM display mapping for the lightweight reports screen (Sprint 9). All
 * values shown here are already computed by the backend; these helpers only
 * format them for display and degrade gracefully for null/empty/malformed
 * values. Isolated from Android so it can be unit-tested directly.
 */
object ReportDisplay {

    const val METHOD_CASH = "CASH"
    const val METHOD_QRIS = "QRIS"
    const val STATUS_PAID = "PAID"
    const val MOVEMENT_SALE_OUT = "SALE_OUT"

    /** Format a decimal-string amount as Rupiah; unknown/malformed becomes "Rp 0". */
    fun money(raw: String?): String {
        val value = raw?.toDoubleOrNull() ?: 0.0
        val format = NumberFormat.getNumberInstance(Locale("in", "ID"))
        format.maximumFractionDigits = 0
        return "Rp ${format.format(value)}"
    }

    /** A safe text fallback for nullable/blank display strings. */
    fun text(raw: String?): String =
        if (raw.isNullOrBlank()) "-" else raw

    /** Format an integer count with a safe zero fallback. */
    fun count(value: Int?): String = (value ?: 0).toString()

    /** Total PAID amount for a payment method, as a decimal string ("0.00" if none). */
    fun paidTotalForMethod(items: List<PaymentSummaryItemDto>?, method: String): String {
        val total = items.orEmpty()
            .filter { it.status == STATUS_PAID && it.method == method }
            .sumOf { it.amountTotal?.toDoubleOrNull() ?: 0.0 }
        return String.format(Locale.US, "%.2f", total)
    }

    /** SALE_OUT quantity from the inventory summary, as a decimal string ("0.00" if none). */
    fun saleOutQty(items: List<InventoryMovementSummaryItemDto>?): String {
        val row = items.orEmpty().firstOrNull { it.movementType == MOVEMENT_SALE_OUT }
        return row?.qtyTotal?.takeIf { it.toDoubleOrNull() != null } ?: "0.00"
    }

    /** User-friendly closing message, distinguishing a fresh close from a replay. */
    fun closingMessage(duplicateReplay: Boolean): String =
        if (duplicateReplay) {
            "Hari ini sudah ditutup sebelumnya."
        } else {
            "Hari ini berhasil ditutup."
        }
}
