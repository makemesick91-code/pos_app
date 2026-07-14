package com.aishtech.poslite

import com.aishtech.poslite.feature.cashier.TenderValidator
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-05 (UIX8C-R136/R137/R139/R140) — the pure tender validator. Blank vs
 * garbage/overflow vs insufficient vs valid are distinct outcomes; only a fully
 * valid, sufficient tender may be submitted; change is whole-rupiah and never
 * negative; the cart/due amount is never mutated.
 */
class TenderValidatorTest {

    private val due = 25_000L

    @Test
    fun blankIsEmptyAndNotSubmittable() {
        assertTrue(TenderValidator.validate("", due) is TenderValidator.Result.Empty)
        assertTrue(TenderValidator.validate(null, due) is TenderValidator.Result.Empty)
        assertTrue(TenderValidator.validate("   ", due) is TenderValidator.Result.Empty)
        assertFalse(TenderValidator.canSubmit(TenderValidator.validate("", due)))
    }

    @Test
    fun garbageIsInvalidNotEmpty() {
        assertTrue(TenderValidator.validate("abc", due) is TenderValidator.Result.Invalid)
        assertFalse(TenderValidator.canSubmit(TenderValidator.validate("abc", due)))
    }

    @Test
    fun overflowInputIsInvalidNeverFabricatedZero() {
        // >19 digits exceeds Long range → parse() yields null → Invalid (never 0).
        val huge = "9".repeat(25)
        assertTrue(TenderValidator.validate(huge, due) is TenderValidator.Result.Invalid)
    }

    @Test
    fun insufficientReportsShortfallAndBlocksSubmit() {
        val result = TenderValidator.validate("20000", due)
        assertTrue(result is TenderValidator.Result.Insufficient)
        assertEquals(5_000L, (result as TenderValidator.Result.Insufficient).shortBy)
        assertFalse(TenderValidator.canSubmit(result))
    }

    @Test
    fun exactTenderIsValidWithZeroChange() {
        val result = TenderValidator.validate("25000", due)
        assertTrue(result is TenderValidator.Result.Valid)
        assertEquals(0L, (result as TenderValidator.Result.Valid).change)
        assertTrue(TenderValidator.canSubmit(result))
    }

    @Test
    fun greaterTenderGivesExactChange() {
        val result = TenderValidator.validate("30000", due)
        result as TenderValidator.Result.Valid
        assertEquals(30_000L, result.tender)
        assertEquals(5_000L, result.change)
    }

    @Test
    fun localeThousandSeparatorsAndRupiahPrefixParse() {
        // Indonesian grouping '.' and a leading "Rp" are accepted; the decimal ','
        // part is discarded (rupiah is whole).
        val result = TenderValidator.validate("Rp 30.000", due)
        result as TenderValidator.Result.Valid
        assertEquals(30_000L, result.tender)
        assertEquals(5_000L, result.change)
    }

    @Test
    fun changeIsNeverNegative() {
        // An insufficient tender never surfaces as Valid, so no negative change can
        // ever be produced from validation.
        for (tender in listOf("0", "1", "24999")) {
            assertFalse(TenderValidator.canSubmit(TenderValidator.validate(tender, due)))
        }
    }
}
