package com.aishtech.poslite.feature.receipt

import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.remote.dto.ReceiptDto

/**
 * UIX-8C-06 — builds an immutable [ReceiptProjection] from a canonical
 * transaction source. It is a pure projector (UIX8C-R171): it never creates,
 * mutates, or recomputes a transaction, and it never fabricates a "synced"
 * state.
 *
 * Two sources feed it, both producing the same projection type so
 * receipt/history/backend parity is a single, testable invariant
 * (UIX8C-R177/R178):
 *  - [fromLocalSale] — a durable local Room transaction (offline/pending path).
 *    Legacy [Double] columns are bridged to whole-rupiah [Long] through the one
 *    sanctioned [RupiahMoney.fromDouble] boundary; no fresh float math is added.
 *  - [fromServerReceipt] — a backend-approved [ReceiptDto] (online/synced path).
 *    Server money arrives as canonical decimal strings ("20000.00"); it is read
 *    to an exact [Long] without going through floating point.
 *
 * Pure and framework-free so it is fully unit-testable on the JVM.
 */
object ReceiptProjector {

    /**
     * Project a durable local sale + its snapshotted items. The state is derived
     * from the canonical sync status — a durable save is OFFLINE_PENDING, never
     * SYNCED (UIX8C-R175); SYNCED requires the recorded server acknowledgement
     * (UIX8C-R176). An unrecognised status fails safe to OFFLINE_PENDING and is
     * never presented as synced.
     */
    fun fromLocalSale(
        sale: LocalOfflineSaleEntity,
        items: List<LocalOfflineSaleItemEntity>,
    ): ReceiptProjection {
        val lines = items.map {
            ReceiptLine(
                productName = it.productName,
                quantity = it.qty,
                unitPrice = RupiahMoney.fromDouble(it.unitPrice),
                lineTotal = RupiahMoney.fromDouble(it.subtotal),
            )
        }
        return ReceiptProjection(
            identity = ReceiptIdentity(
                clientReference = sale.clientReference,
                serverSaleId = sale.serverSaleId,
                localId = sale.localId,
            ),
            state = stateFromSyncStatus(sale.syncStatus),
            businessName = null,
            outletName = null,
            cashierName = null,
            reference = sale.serverInvoiceNumber ?: sale.clientReference,
            dateTime = sale.saleDate,
            lines = lines,
            subtotal = RupiahMoney.fromDouble(sale.subtotal),
            discountTotal = RupiahMoney.fromDouble(sale.discountTotal),
            taxTotal = RupiahMoney.fromDouble(sale.taxTotal),
            grandTotal = RupiahMoney.fromDouble(sale.grandTotal),
            tender = RupiahMoney.fromDouble(sale.paidAmount),
            change = RupiahMoney.fromDouble(sale.changeAmount),
            paymentMethod = PAYMENT_CASH,
        )
    }

    /**
     * Project a backend-approved receipt for an acknowledged sale. [clientReference]
     * is threaded through when known (e.g. the just-synced offline transaction) so
     * the projection stays bound to the one logical transaction; it is never
     * regenerated here (UIX8C-R151).
     *
     * @param synced true when this receipt represents an already-synced offline
     *   sale (SYNCED); false for a fresh online success (ONLINE_SUCCESS).
     */
    fun fromServerReceipt(
        receipt: ReceiptDto,
        clientReference: String? = null,
        synced: Boolean = false,
    ): ReceiptProjection {
        val lines = receipt.items.map {
            ReceiptLine(
                productName = it.productName ?: "-",
                quantity = parseQuantity(it.qty),
                unitPrice = parseServerAmount(it.unitPrice),
                lineTotal = parseServerAmount(it.subtotal),
            )
        }
        val totals = receipt.totals
        val payment = receipt.payments.firstOrNull()
        return ReceiptProjection(
            identity = ReceiptIdentity(
                clientReference = clientReference,
                serverSaleId = receipt.saleId,
                localId = null,
            ),
            state = if (synced) ReceiptTransactionState.SYNCED else ReceiptTransactionState.ONLINE_SUCCESS,
            businessName = receipt.store?.name,
            outletName = receipt.store?.code ?: receipt.store?.name,
            cashierName = receipt.cashier?.name,
            reference = receipt.invoiceNumber ?: clientReference,
            dateTime = receipt.saleDate,
            lines = lines,
            subtotal = parseServerAmount(totals?.subtotal),
            discountTotal = parseServerAmount(totals?.discountTotal),
            taxTotal = parseServerAmount(totals?.taxTotal),
            grandTotal = parseServerAmount(totals?.grandTotal),
            tender = parseServerAmountOrNull(totals?.paidTotal),
            change = parseServerAmountOrNull(totals?.changeTotal),
            paymentMethod = payment?.method ?: PAYMENT_CASH,
        )
    }

    /** Maps a canonical [OfflineSyncStatus] string to a truthful receipt state. */
    fun stateFromSyncStatus(status: String): ReceiptTransactionState = when (status) {
        OfflineSyncStatus.PENDING -> ReceiptTransactionState.OFFLINE_PENDING
        OfflineSyncStatus.SYNCING -> ReceiptTransactionState.SYNCING
        OfflineSyncStatus.SYNCED -> ReceiptTransactionState.SYNCED
        OfflineSyncStatus.FAILED -> ReceiptTransactionState.FAILED
        OfflineSyncStatus.CONFLICT -> ReceiptTransactionState.CONFLICT
        // Fail safe: an unknown status is never claimed as synced (UIX8C-R147).
        else -> ReceiptTransactionState.OFFLINE_PENDING
    }

    /**
     * Read a canonical server decimal-string amount ("20000.00") to exact whole
     * rupiah without floating point. The integer part before the '.' decimal is
     * kept; any fractional part is discarded (rupiah is whole). Returns 0 when the
     * field is genuinely absent.
     */
    private fun parseServerAmount(raw: String?): Long = parseServerAmountOrNull(raw) ?: 0L

    private fun parseServerAmountOrNull(raw: String?): Long? {
        if (raw.isNullOrBlank()) return null
        val integerPart = raw.trim().substringBefore('.').substringBefore(',')
        val digits = integerPart.filter { it.isDigit() }
        return digits.toLongOrNull()
    }

    private fun parseQuantity(raw: String?): Int {
        if (raw.isNullOrBlank()) return 0
        val integerPart = raw.trim().substringBefore('.').substringBefore(',')
        return integerPart.filter { it.isDigit() }.toIntOrNull() ?: 0
    }

    private const val PAYMENT_CASH = "CASH"
}
