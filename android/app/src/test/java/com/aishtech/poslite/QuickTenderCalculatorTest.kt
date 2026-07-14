package com.aishtech.poslite

import com.aishtech.poslite.feature.cashier.QuickTenderCalculator
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-05 (UIX8C-R135/R137/R138) — the pure quick-tender calculator. Every
 * option is a whole-rupiah round-up STRICTLY greater than the due amount, drawn
 * from a natural cash-denomination ladder, de-duplicated, capped, and — critically
 * — overflow-safe so a pathological basket can never wrap to a negative suggestion.
 */
class QuickTenderCalculatorTest {

    @Test
    fun roundUpsAreStrictlyAboveDue() {
        val due = 25_000L
        val options = QuickTenderCalculator.options(due)
        assertEquals(listOf(30_000L, 40_000L, 50_000L), options)
        assertTrue(options.all { it > due })
    }

    @Test
    fun exactMultipleDueSkipsEqualStep() {
        // 50k is a multiple of 5k/10k/50k → those ceilings equal due and are
        // filtered; only strictly-greater round-ups remain.
        assertEquals(listOf(60_000L, 100_000L), QuickTenderCalculator.options(50_000L))
    }

    @Test
    fun optionsAreDistinctSortedAndCapped() {
        val options = QuickTenderCalculator.options(1L)
        assertEquals(options.distinct(), options)
        assertEquals(options.sorted(), options)
        assertTrue(options.size <= QuickTenderCalculator.MAX_OPTIONS)
    }

    @Test
    fun zeroOrNegativeDueHasNoSuggestions() {
        assertEquals(emptyList<Long>(), QuickTenderCalculator.options(0L))
        assertEquals(emptyList<Long>(), QuickTenderCalculator.options(-1L))
    }

    @Test
    fun overflowNearLongMaxNeverWrapsToNegative() {
        // Every candidate round-up overflows → no options, and never a wrapped
        // negative value (UIX8C-R137).
        val options = QuickTenderCalculator.options(Long.MAX_VALUE - 10L)
        assertTrue(options.all { it > 0L })
        assertEquals(emptyList<Long>(), options)
    }
}
