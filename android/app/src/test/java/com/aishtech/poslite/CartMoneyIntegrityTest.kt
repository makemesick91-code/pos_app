package com.aishtech.poslite

import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.feature.cashier.CartItem
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * UIX-8 money foundation — the cart's AUTHORITATIVE arithmetic is whole-rupiah
 * integer (Long) via RupiahMoney. The legacy Double `subtotal()` is only a
 * projection of the integer total, so the two can never disagree and no float
 * math sits on the authoritative path.
 */
class CartMoneyIntegrityTest {

    @Test
    fun `subtotalRupiah is integer-exact over a mixed basket`() {
        val cart = CartRepository().apply {
            addProduct(1L, "Kopi", 12500.0)
            addProduct(2L, "Teh", 8000.0)
            updateQuantity(2L, 3) // 24000
            addProduct(3L, "Roti", 15000.0)
        }
        assertEquals(12500L + 24000L + 15000L, cart.subtotalRupiah())
    }

    @Test
    fun `double subtotal is exactly the projection of the integer total`() {
        val cart = CartRepository().apply {
            addProduct(1L, "A", 9999.0)
            updateQuantity(1L, 7)
        }
        assertEquals(69993L, cart.subtotalRupiah())
        assertEquals(cart.subtotalRupiah().toDouble(), cart.subtotal(), 0.0)
    }

    @Test
    fun `line total uses integer unit price and clamps non-positive quantity`() {
        assertEquals(30000L, CartItem(1L, "X", 10000.0, 3).lineTotalRupiah)
        assertEquals(0L, CartItem(1L, "X", 10000.0, 0).lineTotalRupiah)
    }

    @Test
    fun `empty cart total is zero rupiah`() {
        assertEquals(0L, CartRepository().subtotalRupiah())
    }
}
