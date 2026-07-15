package com.aishtech.poslite.feature.receipt

import com.aishtech.poslite.core.money.RupiahMoney

/**
 * UIX-8C-06 — the canonical, truthful state a receipt is presented for
 * (UIX8C-R146/R184). Each value is semantically distinct so the receipt and the
 * transaction-history surfaces never conflate an offline-queued draft with a
 * server-acknowledged sale.
 *
 * - [ONLINE_SUCCESS] — the sale was acknowledged by the server this checkout.
 * - [OFFLINE_PENDING] — durably saved locally, waiting for synchronization
 *   (never "synced", never "settled" — UIX8C-R147/R175).
 * - [SYNCING] — a sync attempt is in flight.
 * - [SYNCED] — canonical server acknowledgement was durably recorded locally
 *   (UIX8C-R176/R148).
 * - [FAILED] — a transient/retryable sync failure.
 * - [CONFLICT] — the server rejected the transaction; requires resolution and is
 *   never silently presented as success (UIX8C-R160).
 */
enum class ReceiptTransactionState {
    ONLINE_SUCCESS,
    OFFLINE_PENDING,
    SYNCING,
    SYNCED,
    FAILED,
    CONFLICT,
    ;

    /** True only when the server has acknowledged this transaction. */
    val isServerAcknowledged: Boolean
        get() = this == ONLINE_SUCCESS || this == SYNCED

    /** True when the receipt is a locally-durable draft awaiting the server. */
    val isOfflineDraft: Boolean
        get() = this == OFFLINE_PENDING || this == SYNCING || this == FAILED
}

/**
 * Binds a receipt to exactly one logical transaction (UIX8C-R172). A receipt
 * must carry at least one governed identifier so a stale or previous-transaction
 * result can never be shown for the current checkout (UIX8C-R173/R190).
 *
 * - [clientReference] — the stable device idempotency key that survives the
 *   online attempt, offline fallback, retry, restart and reconnect (reused from
 *   UIX-8C-04/05; never regenerated here).
 * - [serverSaleId] — the server identity once the sale is acknowledged.
 * - [localId] — the local Room row, used for durable reopen/reprint.
 */
data class ReceiptIdentity(
    val clientReference: String?,
    val serverSaleId: Long?,
    val localId: Long?,
) {
    init {
        require(clientReference != null || serverSaleId != null || localId != null) {
            "A receipt identity must carry at least one governed transaction identifier."
        }
    }

    /**
     * Two identities describe the same logical transaction when they agree on the
     * stable clientReference or on the server sale id. Amount/timestamp are never
     * used as identity (UIX8C-R182 forbids amount-only dedup).
     */
    fun matches(other: ReceiptIdentity): Boolean {
        if (clientReference != null && other.clientReference != null) {
            return clientReference == other.clientReference
        }
        if (serverSaleId != null && other.serverSaleId != null) {
            return serverSaleId == other.serverSaleId
        }
        if (localId != null && other.localId != null) {
            return localId == other.localId
        }
        return false
    }
}

/** One receipt line, integer-exact whole-rupiah (UIX8C-R177/R179). */
data class ReceiptLine(
    val productName: String,
    val quantity: Int,
    val unitPrice: Long,
    val lineTotal: Long,
) {
    val unitPriceLabel: String get() = RupiahMoney.format(unitPrice)
    val lineTotalLabel: String get() = RupiahMoney.format(lineTotal)
}

/**
 * An immutable projection of one canonical transaction for receipt presentation
 * (UIX8C-R171). Screens render this; they never recompute money or mutate a
 * transaction. All monetary fields are whole-rupiah [Long] (UIX8C-R179); tender
 * and change are nullable so a genuinely unavailable value renders
 * "Tidak tersedia" instead of a fabricated zero (UIX8C-R013).
 */
data class ReceiptProjection(
    val identity: ReceiptIdentity,
    val state: ReceiptTransactionState,
    val businessName: String?,
    val outletName: String?,
    val cashierName: String?,
    val reference: String?,
    val dateTime: String?,
    val lines: List<ReceiptLine>,
    val subtotal: Long,
    val discountTotal: Long,
    val taxTotal: Long,
    val grandTotal: Long,
    val tender: Long?,
    val change: Long?,
    val paymentMethod: String,
) {
    val itemCount: Int get() = lines.size

    val subtotalLabel: String get() = RupiahMoney.format(subtotal)
    val discountLabel: String get() = RupiahMoney.format(discountTotal)
    val taxLabel: String get() = RupiahMoney.format(taxTotal)
    val grandTotalLabel: String get() = RupiahMoney.format(grandTotal)
    val tenderLabel: String get() = RupiahMoney.formatOrUnavailable(tender)
    val changeLabel: String get() = RupiahMoney.formatOrUnavailable(change)

    /** True when this receipt is a durable offline draft (must not claim sync). */
    val isOffline: Boolean get() = state.isOfflineDraft
}
