package com.aishtech.poslite.feature.history

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.repository.OfflineSaleRepository

/**
 * UIX-8C-06 — reconciles local and (future) server transaction records into one
 * deduplicated history list: exactly one row per logical transaction
 * (UIX8C-R181/R182/R186). It is a pure projection over canonical records — it
 * never mutates a sale, payment, or sync state, and never fabricates a synced
 * state.
 *
 * The device currently has no server history-list endpoint, so history is sourced
 * from the local Room queue and the reconciler runs with an empty server list.
 * The merge logic is nonetheless the real, enforced guard: the moment a server
 * feed is introduced, a synced local row and the same server-confirmed
 * transaction collapse into ONE row keyed on the stable clientReference, rather
 * than appearing as duplicates. It is exercised directly with synthetic
 * local+server inputs in the unit tests.
 *
 * Reconciliation rules:
 *  - Group records by [HistoryRecord.mergeKey] (stable clientReference first).
 *  - A group with both a local and a matching server record merges into one row;
 *    if their whole-rupiah totals disagree, the row is flagged CONFLICT and the
 *    evidence is preserved, never silently merged away (UIX8C-R160).
 *  - The displayed state is the most authoritative available: an explicit local
 *    CONFLICT wins; otherwise a server-confirmed or SYNCED record shows SYNCED; a
 *    FAILED row under the bounded retry cap shows RETRY_SCHEDULED.
 *  - Rows are ordered newest-first with a stable tiebreak (UIX8C-R186).
 */
object TransactionHistoryReconciler {

    fun reconcile(
        local: List<HistoryRecord>,
        server: List<HistoryRecord> = emptyList(),
        maxSyncAttempts: Int = OfflineSaleRepository.MAX_SYNC_ATTEMPTS,
    ): List<HistoryRow> {
        val grouped = LinkedHashMap<String, MutableList<HistoryRecord>>()
        (local + server).forEach { record ->
            grouped.getOrPut(record.mergeKey) { mutableListOf() }.add(record)
        }

        return grouped.map { (key, records) ->
            val localRecord = records.firstOrNull { it.source == HistorySource.LOCAL }
            val serverRecord = records.firstOrNull { it.source == HistorySource.SERVER }
            val primary = localRecord ?: serverRecord ?: records.first()

            val amountConflict = localRecord != null && serverRecord != null &&
                localRecord.grandTotal != serverRecord.grandTotal
            val statusConflict = records.any { it.syncStatus == OfflineSyncStatus.CONFLICT }
            val conflict = amountConflict || statusConflict

            HistoryRow(
                key = key,
                displayState = displayState(records, conflict, maxSyncAttempts),
                grandTotal = primary.grandTotal,
                reference = serverRecord?.reference ?: primary.reference,
                dateTime = primary.dateTime,
                createdAt = primary.createdAt,
                localId = localRecord?.localId,
                serverSaleId = serverRecord?.serverSaleId ?: localRecord?.serverSaleId,
                clientReference = primary.clientReference,
                conflict = conflict,
            )
        }.sortedWith(compareByDescending<HistoryRow> { it.createdAt }.thenBy { it.key })
    }

    private fun displayState(
        records: List<HistoryRecord>,
        conflict: Boolean,
        maxSyncAttempts: Int,
    ): HistoryDisplayState {
        if (conflict) return HistoryDisplayState.CONFLICT

        // A server-confirmed record, or a local row the server already acknowledged,
        // is SYNCED. This is the only path to SYNCED (UIX8C-R148/R176).
        val hasServerConfirmed = records.any {
            it.source == HistorySource.SERVER || it.syncStatus == OfflineSyncStatus.SYNCED
        }
        if (hasServerConfirmed) return HistoryDisplayState.SYNCED

        // Otherwise reflect the local sync status truthfully.
        val local = records.firstOrNull { it.source == HistorySource.LOCAL } ?: records.first()
        return when (local.syncStatus) {
            OfflineSyncStatus.PENDING -> HistoryDisplayState.PENDING
            OfflineSyncStatus.SYNCING -> HistoryDisplayState.SYNCING
            OfflineSyncStatus.FAILED ->
                if (local.syncAttemptCount < maxSyncAttempts) {
                    HistoryDisplayState.RETRY_SCHEDULED
                } else {
                    HistoryDisplayState.FAILED
                }
            OfflineSyncStatus.CONFLICT -> HistoryDisplayState.CONFLICT
            else -> HistoryDisplayState.UNKNOWN
        }
    }
}
