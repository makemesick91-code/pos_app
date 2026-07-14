package com.aishtech.poslite

import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.feature.cashier.CashierViewModel
import com.aishtech.poslite.feature.cashier.CashierViewModel.ProductsState
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * UIX-8B — truthful product-list empty state and the cashier success-summary
 * money contract. These lock two remediations:
 *  1. A no-result search distinguishes an empty catalog (needs sync) from a
 *     no-match term (UIX8B-R023), instead of a silent empty swap.
 *  2. The online success summary renders the canonical whole-rupiah value or
 *     "Tidak tersedia" — never the old float `toDoubleOrNull() ?: 0.0` that
 *     fabricated a 0 (UIX8B-R044/R047/R063).
 */
class CashierProductsStateTest {

    @Test
    fun blankQueryWithNoResultsIsEmptyCatalog() {
        assertEquals(ProductsState.EmptyCatalog, CashierViewModel.emptyProductsState(""))
        assertEquals(ProductsState.EmptyCatalog, CashierViewModel.emptyProductsState("   "))
    }

    @Test
    fun nonBlankQueryWithNoResultsIsNoMatch() {
        val state = CashierViewModel.emptyProductsState("kopi")
        assertEquals(ProductsState.NoMatch("kopi"), state)
    }

    @Test
    fun successSummaryRendersCanonicalTotalNotFabricatedZero() {
        // A missing server total must read as unavailable, never "Rp 0".
        assertEquals(RupiahMoney.UNAVAILABLE, RupiahMoney.formatOrUnavailable(RupiahMoney.parse(null)))
        assertEquals(RupiahMoney.UNAVAILABLE, RupiahMoney.formatOrUnavailable(RupiahMoney.parse("")))
        // A present canonical string is formatted through the single formatter and
        // is NOT mis-parsed as a decimal (the old float path read "25000" wrong).
        assertEquals("Rp 25.000", RupiahMoney.formatOrUnavailable(RupiahMoney.parse("25000")))
    }
}
