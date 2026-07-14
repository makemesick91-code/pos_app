package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CashPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleItemRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.SaleDto
import com.aishtech.poslite.feature.cashier.CartItem
import java.util.Locale

/**
 * Submits the local cart to the backend as an online CASH sale.
 *
 * The repository NEVER mutates the cart — clearing (on success) or keeping it
 * (on failure) is a UI-layer decision so the two concerns stay separable and
 * the Sprint 4 rule "clear only on success" is enforced where the result is
 * observed. Money totals are recomputed server-side; the app only sends line
 * items and the cash tendered.
 */
class SalesRepository(private val api: PosApiService) {

    suspend fun checkoutCash(
        items: List<CartItem>,
        paidAmount: Double,
        clientReference: String? = null,
        clientCreatedAt: String? = null,
    ): ResultState<SaleDto> {
        if (items.isEmpty()) {
            return ResultState.Error("Keranjang kosong.")
        }

        val request = CreateSaleRequestDto(
            items = items.map {
                CreateSaleItemRequestDto(productId = it.productId, qty = it.quantity)
            },
            payment = CashPaymentRequestDto(paidAmount = formatAmount(paidAmount)),
            // UIX7-R054/R055 — an online checkout carries a stable client_reference so
            // that a retry after a lost response (e.g. a read timeout AFTER the server
            // has already committed the sale) is deduped by the backend
            // (SaleService::createCashSale) instead of creating a second sale. The
            // whole backend chain (StoreSaleRequest → SaleService → unique index) has
            // supported this since Sprint 7; only the online client had omitted it.
            source = clientReference?.let { "ANDROID_ONLINE" },
            clientReference = clientReference,
            clientCreatedAt = clientCreatedAt,
        )

        return try {
            val response = api.createSale(request)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Checkout gagal (${response.code()}). Keranjang dipertahankan.")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Checkout gagal. Periksa koneksi.")
        }
    }

    private fun formatAmount(value: Double): String =
        String.format(Locale.US, "%.2f", value)
}
