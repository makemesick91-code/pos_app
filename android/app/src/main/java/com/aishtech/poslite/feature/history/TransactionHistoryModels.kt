package com.aishtech.poslite.feature.history

/**
 * UIX-8C-06 — the truthful, distinct display state of one transaction-history
 * row (UIX8C-R184). RETRY_SCHEDULED is derived (a FAILED row still under the
 * bounded retry cap), so a row awaiting an automatic retry is not shown as a
 * terminal failure.
 */
enum class HistoryDisplayState {
    PENDING,
    SYNCING,
    RETRY_SCHEDULED,
    SYNCED,
    FAILED,
    CONFLICT,
    UNKNOWN,
}

/** Where a reconciler input record came from. */
enum class HistorySource { LOCAL, SERVER }

/**
 * A normalized transaction record fed into [TransactionHistoryReconciler] from
 * either the local Room queue or (in future) a server history feed. Identity is
 * the stable [clientReference] first, then the [serverSaleId]; amount/timestamp
 * are never identity (UIX8C-R182). [grandTotal] is whole-rupiah [Long].
 */
data class HistoryRecord(
    val source: HistorySource,
    val clientReference: String?,
    val serverSaleId: Long?,
    val localId: Long?,
    val syncStatus: String,
    val syncAttemptCount: Int,
    val grandTotal: Long,
    val reference: String?,
    val dateTime: String,
    val createdAt: Long,
) {
    /**
     * The logical-transaction key. Prefer the stable clientReference; fall back
     * to the server sale id; finally the local id. Never amount/timestamp.
     */
    val mergeKey: String
        get() = when {
            clientReference != null -> "cref:$clientReference"
            serverSaleId != null -> "srv:$serverSaleId"
            localId != null -> "local:$localId"
            else -> "unknown:${createdAt}"
        }
}

/**
 * One reconciled, deduplicated history row — exactly one per logical transaction
 * (UIX8C-R181). Carries the identity needed to open the durable detail/reprint
 * screen and a [conflict] flag when local and server disagree (UIX8C-R160).
 */
data class HistoryRow(
    val key: String,
    val displayState: HistoryDisplayState,
    val grandTotal: Long,
    val reference: String?,
    val dateTime: String,
    val createdAt: Long,
    val localId: Long?,
    val serverSaleId: Long?,
    val clientReference: String?,
    val conflict: Boolean,
)
