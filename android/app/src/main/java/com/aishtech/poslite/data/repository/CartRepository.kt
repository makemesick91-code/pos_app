package com.aishtech.poslite.data.repository

import com.aishtech.poslite.feature.cashier.CartItem

/**
 * In-memory, cash-first cart (Sprint 3 is local-only — no backend submission).
 *
 * Framework-free so it is unit-testable on the JVM. Insertion order is
 * preserved for a stable cashier list. Quantities are clamped to >= 0; a qty of
 * 0 removes the line.
 */
class CartRepository {

    private val items = LinkedHashMap<Long, CartItem>()

    /** Adds one unit of the product, incrementing quantity if already present. */
    fun addProduct(productId: Long, name: String, unitPrice: Double): CartItem {
        val existing = items[productId]
        val updated = existing?.copy(quantity = existing.quantity + 1)
            ?: CartItem(productId = productId, name = name, unitPrice = unitPrice, quantity = 1)
        items[productId] = updated
        return updated
    }

    /** Sets an absolute quantity. A quantity <= 0 removes the line. */
    fun updateQuantity(productId: Long, quantity: Int) {
        val existing = items[productId] ?: return
        if (quantity <= 0) {
            items.remove(productId)
        } else {
            items[productId] = existing.copy(quantity = quantity)
        }
    }

    fun removeProduct(productId: Long) {
        items.remove(productId)
    }

    fun clear() {
        items.clear()
    }

    fun items(): List<CartItem> = items.values.toList()

    fun itemCount(): Int = items.values.sumOf { it.quantity }

    fun subtotal(): Double = items.values.sumOf { it.lineTotal }

    fun isEmpty(): Boolean = items.isEmpty()
}
