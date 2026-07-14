package com.aishtech.poslite

import com.aishtech.poslite.feature.cashier.PaymentSheetFragment
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8B — quick cash-tender suggestions (UIX8B-R047/R049). Every suggestion is
 * strictly greater than the amount due (the exact amount is the separate "Uang
 * Pas" button), and values come from a natural cash-denomination ladder — never
 * a fabricated or below-due amount.
 */
class PaymentTenderTest {

    @Test
    fun suggestionsAreRoundUpsStrictlyAboveDue() {
        val due = 25_000L
        val quicks = PaymentSheetFragment.quickTenders(due)
        assertEquals(listOf(30_000L, 40_000L, 50_000L), quicks)
        assertTrue(quicks.all { it > due })
    }

    @Test
    fun exactMultipleDueSkipsEqualStep() {
        // 50k is already a multiple of 5k/10k/50k → those ceilings equal due and
        // must be filtered out; only strictly-greater round-ups remain.
        val quicks = PaymentSheetFragment.quickTenders(50_000L)
        assertEquals(listOf(60_000L, 100_000L), quicks)
    }

    @Test
    fun zeroOrNegativeDueHasNoSuggestions() {
        assertEquals(emptyList<Long>(), PaymentSheetFragment.quickTenders(0L))
        assertEquals(emptyList<Long>(), PaymentSheetFragment.quickTenders(-1L))
    }
}
