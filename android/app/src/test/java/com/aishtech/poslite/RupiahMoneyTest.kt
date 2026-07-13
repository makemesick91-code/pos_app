package com.aishtech.poslite

import com.aishtech.poslite.core.money.RupiahMoney
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-7 (UIX7-R018/R019) — the canonical whole-rupiah money type must be
 * integer-exact and never drift the way Double arithmetic can. These are pure
 * JVM tests, the only automated gate available for this app.
 */
class RupiahMoneyTest {

    @Test
    fun `parse strips grouping symbol and currency prefix`() {
        assertEquals(25000L, RupiahMoney.parse("Rp 25.000"))
        assertEquals(25000L, RupiahMoney.parse("25000"))
        assertEquals(25000L, RupiahMoney.parse(" 25.000 "))
    }

    @Test
    fun `parse discards an incidental decimal part - rupiah is whole`() {
        // ',' is the Indonesian decimal separator; the fractional part is dropped.
        assertEquals(25000L, RupiahMoney.parse("25000,50"))
        assertEquals(25000L, RupiahMoney.parse("25000,99"))
    }

    @Test
    fun `parse rejects blank and non-numeric input rather than fabricating zero`() {
        assertNull(RupiahMoney.parse(null))
        assertNull(RupiahMoney.parse(""))
        assertNull(RupiahMoney.parse("   "))
        assertNull(RupiahMoney.parse("abc"))
        assertNull(RupiahMoney.parse("Rp"))
    }

    @Test
    fun `fromDouble rounds to the nearest whole rupiah`() {
        assertEquals(25000L, RupiahMoney.fromDouble(25000.0))
        assertEquals(25000L, RupiahMoney.fromDouble(24999.6))
        assertEquals(15000L, RupiahMoney.fromDouble(15000.4))
    }

    @Test
    fun `lineTotal is integer-exact and clamps non-positive quantity`() {
        assertEquals(30000L, RupiahMoney.lineTotal(10000L, 3))
        assertEquals(0L, RupiahMoney.lineTotal(10000L, 0))
        assertEquals(0L, RupiahMoney.lineTotal(10000L, -2))
    }

    @Test
    fun `subtotal over many lines never drifts`() {
        // A basket that classic 0.1-style float math could nudge off by a rupiah.
        val lines = List(1000) { RupiahMoney.lineTotal(1999L, 1) }
        assertEquals(1_999_000L, RupiahMoney.subtotal(lines))
    }

    @Test
    fun `change and sufficiency are computed on integers`() {
        assertEquals(5000L, RupiahMoney.change(paid = 25000L, total = 20000L))
        assertEquals(0L, RupiahMoney.change(paid = 20000L, total = 20000L))
        assertEquals(-1000L, RupiahMoney.change(paid = 19000L, total = 20000L))
        assertTrue(RupiahMoney.isSufficient(paid = 20000L, total = 20000L))
        assertFalse(RupiahMoney.isSufficient(paid = 19999L, total = 20000L))
    }

    @Test
    fun `format renders id-ID grouping with the currency prefix`() {
        assertEquals("Rp 25.000", RupiahMoney.format(25000L))
        assertEquals("Rp 0", RupiahMoney.format(0L))
        assertEquals("Rp 1.250.000", RupiahMoney.format(1_250_000L))
    }

    @Test
    fun `null amount renders unavailable, never a fake zero`() {
        assertEquals(RupiahMoney.UNAVAILABLE, RupiahMoney.formatOrUnavailable(null))
        assertEquals("Rp 0", RupiahMoney.formatOrUnavailable(0L))
    }
}
