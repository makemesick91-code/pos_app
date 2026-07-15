package com.aishtech.poslite.feature.cashier

/**
 * UIX-8C-05 (UIX8C-R135/R138) — the single, pure, deterministic source of the
 * quick-tender shortcuts shown on the cash payment sheet.
 *
 * Each option is a whole-rupiah value derived from — and validated against — the
 * canonical [amountDue]: a natural cash-denomination round-up strictly GREATER
 * than the due amount (the exact-amount "Uang Pas" case is a separate button and
 * is never produced here). The calculation is integer-exact and OVERFLOW-SAFE:
 * a round-up that would exceed [Long] range is dropped rather than wrapping to a
 * negative or nonsensical suggestion (UIX8C-R137).
 *
 * Pure and framework-free so it is fully JVM-unit-testable without a device.
 */
object QuickTenderCalculator {

    /** Natural Indonesian cash-denomination ladder for round-up shortcuts. */
    private val STEPS = listOf(5_000L, 10_000L, 20_000L, 50_000L, 100_000L)

    /** At most this many quick-tender chips are offered (keeps the sheet compact). */
    const val MAX_OPTIONS = 3

    /**
     * The quick-tender shortcuts for [amountDue], strictly greater than it, in
     * ascending order, de-duplicated, capped at [MAX_OPTIONS]. Returns empty for a
     * non-positive due amount (nothing to tender) or when every candidate would
     * overflow.
     */
    fun options(amountDue: Long): List<Long> {
        if (amountDue <= 0L) return emptyList()
        return STEPS
            .mapNotNull { step -> roundUpStrictlyAbove(amountDue, step) }
            .filter { it > amountDue }
            .distinct()
            .sorted()
            .take(MAX_OPTIONS)
    }

    /**
     * Ceil([due] / [step]) * [step], overflow-safe. Returns null if any
     * intermediate step would exceed [Long] range so an unrealistic basket can
     * never produce a wrapped (negative) suggestion.
     */
    private fun roundUpStrictlyAbove(due: Long, step: Long): Long? {
        // Guard the (due + step - 1) addition.
        if (due > Long.MAX_VALUE - (step - 1L)) return null
        val ceilDiv = (due + step - 1L) / step
        // Guard the ceilDiv * step multiplication.
        if (ceilDiv > Long.MAX_VALUE / step) return null
        return ceilDiv * step
    }
}
