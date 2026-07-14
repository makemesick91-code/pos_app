package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.feature.cashier.CashierViewModel.ProductsState
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * UIX-8C-03 — filter-aware truthful empty state (UIX8C-R067/R068). An active
 * category with no results is a "no match", never presented as an empty catalog;
 * only a blank query with NO active filter is a genuinely empty catalog.
 */
class CashierFilterStateTest {

    @Test
    fun blankQueryNoFilterIsEmptyCatalog() {
        assertEquals(ProductsState.EmptyCatalog, CashierViewModel.emptyProductsState("", filterActive = false))
        assertEquals(ProductsState.EmptyCatalog, CashierViewModel.emptyProductsState("   ", filterActive = false))
    }

    @Test
    fun blankQueryWithActiveCategoryIsNoMatch() {
        // A category is selected but empty → the catalog is NOT empty, this
        // category just has no products. Never show "sync to load catalog".
        assertEquals(ProductsState.NoMatch(""), CashierViewModel.emptyProductsState("", filterActive = true))
    }

    @Test
    fun nonBlankQueryIsAlwaysNoMatch() {
        assertEquals(ProductsState.NoMatch("kopi"), CashierViewModel.emptyProductsState("kopi", filterActive = false))
        assertEquals(ProductsState.NoMatch("kopi"), CashierViewModel.emptyProductsState("kopi", filterActive = true))
    }

    @Test
    fun legacyOverloadDefaultsToNoFilter() {
        assertEquals(ProductsState.EmptyCatalog, CashierViewModel.emptyProductsState(""))
    }
}
