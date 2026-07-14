package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.core.money.RupiahMoney

/**
 * A single line in the cash-first cart.
 *
 * `unitPrice` is a legacy [Double] carried straight from the catalog entity
 * (which still stores prices as Double). UIX-8 (UIX7-R018/R019, UIX8 money
 * foundation) makes the CART ARITHMETIC integer-exact: [unitPriceRupiah] and
 * [lineTotalRupiah] are the authoritative whole-rupiah values, computed through
 * [RupiahMoney] so a basket total can never drift a rupiah via float math. The
 * remaining [lineTotal] `Double` is a pure projection of the integer value kept
 * only for the legacy display/DTO edges — it is NOT an independent calculation,
 * so there is a single money source of truth.
 */
data class CartItem(
    val productId: Long,
    val name: String,
    val unitPrice: Double,
    val quantity: Int,
) {
    /** Authoritative integer unit price in whole rupiah. */
    val unitPriceRupiah: Long get() = RupiahMoney.fromDouble(unitPrice)

    /** Authoritative integer line total (quantity clamped at zero). */
    val lineTotalRupiah: Long get() = RupiahMoney.lineTotal(unitPriceRupiah, quantity)

    /** Legacy Double projection of [lineTotalRupiah] for display/DTO edges only. */
    val lineTotal: Double get() = lineTotalRupiah.toDouble()
}
