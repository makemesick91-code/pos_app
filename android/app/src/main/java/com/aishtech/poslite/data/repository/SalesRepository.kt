package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.network.TransportFailureClassifier
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

    /**
     * UIX-8C-04 (UIX8C-R098..R103) — the typed result of a single online CASH
     * submit attempt, rich enough for the ViewModel to decide whether a durable
     * offline fallback is *governed and safe*:
     *
     *  - [Success]            — the server acknowledged a canonical sale.
     *  - [Rejected]           — the server was REACHABLE and returned a canonical
     *                           rejection (any HTTP status). NEVER offline
     *                           (UIX8C-R099..R102): reachability is proven by a
     *                           received response.
     *  - [TransportUnavailable] — a governed transport/unavailability failure
     *                           (DNS/timeout/connect/reset). Eligible for the
     *                           durable offline CASH fallback (UIX8C-R098).
     *  - [Failed]             — a security (TLS) or unknown/programming error.
     *                           NEVER offline (UIX8C-R103).
     */
    sealed class CheckoutOutcome {
        data class Success(val sale: SaleDto) : CheckoutOutcome()
        data class Rejected(val code: Int, val message: String) : CheckoutOutcome()
        data class TransportUnavailable(val reason: String) : CheckoutOutcome()
        data class Failed(val message: String) : CheckoutOutcome()
    }

    /**
     * Attempt an online CASH sale and return a [CheckoutOutcome]. The caller (the
     * ViewModel) owns the offline-fallback decision so that the "clear cart only
     * after a durable outcome" rule and the stable-clientReference lifecycle stay
     * in one place. This repository still NEVER mutates the cart.
     *
     * A received HTTP response — successful OR an error status — always means the
     * server was reachable, so an error status maps to [CheckoutOutcome.Rejected]
     * and is never eligible for offline fallback. Only a *thrown* transport
     * failure is classified by [TransportFailureClassifier]; an eligible one maps
     * to [CheckoutOutcome.TransportUnavailable].
     */
    suspend fun submitCash(
        items: List<CartItem>,
        paidAmount: Long,
        clientReference: String,
        clientCreatedAt: String? = null,
    ): CheckoutOutcome {
        if (items.isEmpty()) {
            return CheckoutOutcome.Failed("Keranjang kosong.")
        }

        val request = CreateSaleRequestDto(
            items = items.map {
                CreateSaleItemRequestDto(productId = it.productId, qty = it.quantity)
            },
            payment = CashPaymentRequestDto(paidAmount = formatAmount(paidAmount)),
            source = "ANDROID_ONLINE",
            clientReference = clientReference,
            clientCreatedAt = clientCreatedAt,
        )

        return try {
            val response = api.createSale(request)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                CheckoutOutcome.Success(body.data)
            } else {
                // Server reachable + canonical rejection → NEVER offline success.
                CheckoutOutcome.Rejected(
                    code = response.code(),
                    message = "Checkout ditolak server (${response.code()}). Keranjang dipertahankan.",
                )
            }
        } catch (e: Exception) {
            when (val c = TransportFailureClassifier.classify(e)) {
                is TransportFailureClassifier.Classification.Eligible ->
                    CheckoutOutcome.TransportUnavailable(c.reason)
                is TransportFailureClassifier.Classification.Ineligible ->
                    CheckoutOutcome.Failed(c.reason)
            }
        }
    }

    suspend fun checkoutCash(
        items: List<CartItem>,
        paidAmount: Long,
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

    // UIX-8 — the cash tendered is whole-rupiah Long; the backend sale contract
    // still expects a 2-decimal string, so format the integer as "<rupiah>.00"
    // (no float arithmetic, exact) to keep the wire contract unchanged.
    private fun formatAmount(value: Long): String =
        String.format(Locale.US, "%d.00", value)
}
