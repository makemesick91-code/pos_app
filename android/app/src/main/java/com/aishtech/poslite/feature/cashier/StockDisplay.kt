package com.aishtech.poslite.feature.cashier

import java.util.Locale

/**
 * Pure, side-effect-free mapping of a backend `current_stock` string into a
 * lightweight cashier display. Sprint 8 keeps stock visibility informational:
 * unknown stock shows "-", non-positive stock raises a warning flag. No network,
 * no state — trivially unit-testable and cheap on older devices.
 */
object StockDisplay {

    const val UNKNOWN_LABEL: String = "Stok: -"

    /** A human label like "Stok: 12" or "Stok: -" when the value is unknown. */
    fun label(currentStock: String?): String {
        val value = parse(currentStock) ?: return UNKNOWN_LABEL
        return "Stok: ${formatQty(value)}"
    }

    /** True when stock is known and at or below zero (out-of-stock warning). */
    fun isWarning(currentStock: String?): Boolean {
        val value = parse(currentStock) ?: return false
        return value <= 0.0
    }

    /** True when the backend did not report a usable stock figure. */
    fun isUnknown(currentStock: String?): Boolean = parse(currentStock) == null

    private fun parse(raw: String?): Double? {
        val trimmed = raw?.trim().orEmpty()
        if (trimmed.isEmpty()) return null
        return trimmed.toDoubleOrNull()
    }

    private fun formatQty(value: Double): String {
        // Drop the ".00" tail for whole numbers; keep 2 decimals otherwise.
        return if (value % 1.0 == 0.0) {
            value.toLong().toString()
        } else {
            String.format(Locale.US, "%.2f", value)
        }
    }
}
