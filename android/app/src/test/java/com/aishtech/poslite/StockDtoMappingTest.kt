package com.aishtech.poslite

import com.aishtech.poslite.feature.cashier.StockDisplay
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sprint 8 — pure-JVM tests for the informational stock display mapping. Stock
 * is only ever shown, never used to block a sale, so the mapping must degrade
 * gracefully for unknown/malformed values.
 */
class StockDtoMappingTest {

    @Test
    fun `maps current stock string to a label`() {
        assertEquals("Stok: 12", StockDisplay.label("12.00"))
        assertEquals("Stok: 3.50", StockDisplay.label("3.50"))
    }

    @Test
    fun `unknown stock is displayed as dash`() {
        assertEquals("Stok: -", StockDisplay.label(null))
        assertEquals("Stok: -", StockDisplay.label(""))
        assertEquals("Stok: -", StockDisplay.label("not-a-number"))
        assertTrue(StockDisplay.isUnknown(null))
    }

    @Test
    fun `zero and negative stock produce a warning state`() {
        assertTrue(StockDisplay.isWarning("0.00"))
        assertTrue(StockDisplay.isWarning("-2.00"))
        assertFalse(StockDisplay.isWarning("5.00"))
    }

    @Test
    fun `unknown stock is never a warning`() {
        assertFalse(StockDisplay.isWarning(null))
        assertFalse(StockDisplay.isWarning("abc"))
    }
}
