package com.aishtech.poslite

import com.aishtech.poslite.data.repository.CartRepository
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Pure-JVM tests for the cash-first cart foundation (Sprint 3).
 */
class CartRepositoryTest {

    @Test
    fun `add product adds an item`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)

        assertEquals(1, cart.items().size)
        assertEquals(1, cart.itemCount())
        assertEquals(10000.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `adding same product increments quantity`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.addProduct(1L, "Kopi", 10000.0)

        assertEquals(1, cart.items().size)
        assertEquals(2, cart.items().first().quantity)
        assertEquals(20000.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `updating quantity changes the total`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.updateQuantity(1L, 5)

        assertEquals(5, cart.items().first().quantity)
        assertEquals(50000.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `updating quantity to zero removes the line`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.updateQuantity(1L, 0)

        assertTrue(cart.isEmpty())
        assertEquals(0.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `remove item works`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.addProduct(2L, "Teh", 5000.0)
        cart.removeProduct(1L)

        assertEquals(1, cart.items().size)
        assertEquals(2L, cart.items().first().productId)
        assertEquals(5000.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `clear cart empties everything`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.addProduct(2L, "Teh", 5000.0)
        cart.clear()

        assertTrue(cart.isEmpty())
        assertEquals(0, cart.itemCount())
        assertEquals(0.0, cart.subtotal(), 0.001)
    }

    @Test
    fun `subtotal sums multiple lines and quantities`() {
        val cart = CartRepository()
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.addProduct(1L, "Kopi", 10000.0)
        cart.addProduct(2L, "Teh", 5000.0)

        // 2 x 10000 + 1 x 5000
        assertEquals(25000.0, cart.subtotal(), 0.001)
        assertEquals(3, cart.itemCount())
    }
}
