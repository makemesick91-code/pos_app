package com.aishtech.poslite.core.money

import java.text.NumberFormat
import java.util.Locale

/**
 * UIX-7 (UIX7-R018/R019) — the canonical money representation for the cashier
 * surface. Indonesian Rupiah has NO minor unit, so every monetary amount is a
 * whole-rupiah [Long]. This type exists so cashier arithmetic (line totals,
 * subtotal, change) and display never go through unsafe floating-point math,
 * which can drift a total by a rupiah on large baskets.
 *
 * Pure and framework-free so it is fully unit-testable on the JVM (no device,
 * no Android runtime) — which is the only build gate available for this app.
 *
 * This is an ADDITIVE utility: it does not recompute business truth. The
 * backend billing/sales services remain the authority for persisted amounts;
 * this only guarantees the on-device presentation and cash-change arithmetic
 * are integer-exact and formatted consistently.
 */
object RupiahMoney {

    /** Prefix used by the single canonical formatter. */
    const val SYMBOL = "Rp"

    /** Rendered when an amount is genuinely unavailable (never a fabricated 0). */
    const val UNAVAILABLE = "Tidak tersedia"

    private val idLocale = Locale("in", "ID")

    /**
     * Parse a cashier-entered amount into whole rupiah. Accepts grouping
     * characters and a leading "Rp" ("Rp 25.000", "25000", "25.000 "), tolerating
     * an incidental trailing decimal part which is DISCARDED (rupiah is whole).
     * Returns null for blank/garbage input so the caller can reject it — never a
     * fabricated zero.
     */
    fun parse(raw: String?): Long? {
        if (raw == null) return null
        // Indonesian number formatting uses '.' as the THOUSANDS separator and ','
        // as the decimal separator. Keep the integer portion by cutting at the
        // first ',' (decimal), then strip all remaining non-digits ('.' grouping,
        // spaces, "Rp"). Rupiah is whole, so any decimal part is discarded.
        val integerPart = raw.trim().substringBefore(',')
        val digits = integerPart.filter { it.isDigit() }
        if (digits.isEmpty()) return null
        return digits.toLongOrNull()
    }

    /**
     * Convert a legacy [Double] whole-rupiah value (the app still stores some
     * amounts as Double) into an exact [Long] by rounding to the nearest rupiah.
     * The bridge keeps existing call sites correct while float storage is phased
     * out, and never introduces a fresh float calculation.
     */
    fun fromDouble(value: Double): Long = Math.round(value)

    /** Exact integer line total. Quantity below zero is clamped to zero. */
    fun lineTotal(unitPrice: Long, quantity: Int): Long =
        if (quantity <= 0) 0L else unitPrice * quantity

    /** Exact integer subtotal over pre-computed line totals. */
    fun subtotal(lineTotals: List<Long>): Long = lineTotals.sum()

    /**
     * Change owed to the customer. A non-negative result means the paid amount
     * covers the total; a negative result means underpayment and the caller must
     * reject the checkout rather than present a fabricated success.
     */
    fun change(paid: Long, total: Long): Long = paid - total

    /** True only when the paid amount fully covers the total. */
    fun isSufficient(paid: Long, total: Long): Boolean = paid >= total

    /**
     * The single canonical money formatter for the cashier surface, e.g.
     * 25000 -> "Rp 25.000". Adopting this everywhere removes the duplicated
     * NumberFormat blocks scattered across the activities (UIX7-R019/R029).
     */
    fun format(amount: Long): String {
        val format = NumberFormat.getNumberInstance(idLocale)
        format.maximumFractionDigits = 0
        return "$SYMBOL ${format.format(amount)}"
    }

    /** Formats a nullable amount, rendering [UNAVAILABLE] instead of a fake 0. */
    fun formatOrUnavailable(amount: Long?): String =
        if (amount == null) UNAVAILABLE else format(amount)
}
