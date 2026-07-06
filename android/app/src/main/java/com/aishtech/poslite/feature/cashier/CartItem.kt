package com.aishtech.poslite.feature.cashier

/**
 * A single line in the cash-first cart. Local-only for Sprint 3 — never sent
 * to the backend (sales submission arrives in Sprint 4).
 */
data class CartItem(
    val productId: Long,
    val name: String,
    val unitPrice: Double,
    val quantity: Int,
) {
    val lineTotal: Double get() = unitPrice * quantity
}
