package com.aishtech.poslite

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.feature.history.HistoryDisplayState
import com.aishtech.poslite.feature.history.HistoryRecord
import com.aishtech.poslite.feature.history.HistorySource
import com.aishtech.poslite.feature.history.TransactionHistoryReconciler
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-06 — local/server reconciliation + deduplication (UIX8C-R181/R182/R186).
 * One logical transaction is exactly one history row; a local pending row and the
 * same server-confirmed transaction merge on the stable clientReference; a payload
 * mismatch surfaces CONFLICT rather than silently merging.
 */
class TransactionHistoryReconcilerTest {

    private fun local(
        cref: String,
        status: String = OfflineSyncStatus.PENDING,
        total: Long = 30000,
        attempts: Int = 0,
        serverSaleId: Long? = null,
        createdAt: Long = 1000,
        localId: Long = 1,
    ) = HistoryRecord(
        source = HistorySource.LOCAL, clientReference = cref, serverSaleId = serverSaleId,
        localId = localId, syncStatus = status, syncAttemptCount = attempts, grandTotal = total,
        reference = cref, dateTime = "d", createdAt = createdAt,
    )

    private fun server(
        cref: String?,
        serverSaleId: Long,
        total: Long = 30000,
        createdAt: Long = 1000,
    ) = HistoryRecord(
        source = HistorySource.SERVER, clientReference = cref, serverSaleId = serverSaleId,
        localId = null, syncStatus = OfflineSyncStatus.SYNCED, syncAttemptCount = 0, grandTotal = total,
        reference = "INV-$serverSaleId", dateTime = "d", createdAt = createdAt,
    )

    @Test fun localPendingOnly_isOnePendingRow() {
        val rows = TransactionHistoryReconciler.reconcile(listOf(local("a")))
        assertEquals(1, rows.size)
        assertEquals(HistoryDisplayState.PENDING, rows.single().displayState)
        assertFalse(rows.single().conflict)
    }

    @Test fun localAndServer_sameReference_mergeToOneSyncedRow() {
        val rows = TransactionHistoryReconciler.reconcile(
            local = listOf(local("a", OfflineSyncStatus.SYNCED, serverSaleId = 5)),
            server = listOf(server("a", serverSaleId = 5)),
        )
        assertEquals("one logical transaction = one row", 1, rows.size)
        assertEquals(HistoryDisplayState.SYNCED, rows.single().displayState)
        assertFalse(rows.single().conflict)
        assertEquals(1L, rows.single().localId)
        assertEquals(5L, rows.single().serverSaleId)
    }

    @Test fun serverOnly_isOneSyncedRow() {
        val rows = TransactionHistoryReconciler.reconcile(emptyList(), listOf(server("a", 5)))
        assertEquals(1, rows.size)
        assertEquals(HistoryDisplayState.SYNCED, rows.single().displayState)
    }

    @Test fun differentReferences_doNotMerge() {
        val rows = TransactionHistoryReconciler.reconcile(
            listOf(local("a", localId = 1), local("b", localId = 2, createdAt = 2000)),
        )
        assertEquals(2, rows.size)
    }

    @Test fun sameReference_mismatchedTotal_isConflict_notSilentMerge() {
        val rows = TransactionHistoryReconciler.reconcile(
            local = listOf(local("a", OfflineSyncStatus.SYNCING, total = 30000, serverSaleId = 5)),
            server = listOf(server("a", serverSaleId = 5, total = 99999)),
        )
        assertEquals(1, rows.size)
        assertTrue("payload mismatch must surface as conflict", rows.single().conflict)
        assertEquals(HistoryDisplayState.CONFLICT, rows.single().displayState)
    }

    @Test fun localConflictStatus_isConflictRow() {
        val rows = TransactionHistoryReconciler.reconcile(listOf(local("a", OfflineSyncStatus.CONFLICT)))
        assertEquals(HistoryDisplayState.CONFLICT, rows.single().displayState)
        assertTrue(rows.single().conflict)
    }

    @Test fun failedUnderCap_isRetryScheduled_atCapIsFailed() {
        val under = TransactionHistoryReconciler.reconcile(
            listOf(local("a", OfflineSyncStatus.FAILED, attempts = 1)), maxSyncAttempts = 5,
        )
        assertEquals(HistoryDisplayState.RETRY_SCHEDULED, under.single().displayState)

        val atCap = TransactionHistoryReconciler.reconcile(
            listOf(local("a", OfflineSyncStatus.FAILED, attempts = 5)), maxSyncAttempts = 5,
        )
        assertEquals(HistoryDisplayState.FAILED, atCap.single().displayState)
    }

    @Test fun rows_areOrderedNewestFirst_andStable() {
        val rows = TransactionHistoryReconciler.reconcile(
            listOf(
                local("old", createdAt = 100, localId = 1),
                local("new", createdAt = 900, localId = 2),
            ),
        )
        assertEquals("new", rows.first().clientReference)
        assertEquals("old", rows.last().clientReference)
    }

    @Test fun repeatedReconcile_isIdempotent_noDuplicates() {
        val input = listOf(local("a", OfflineSyncStatus.SYNCED, serverSaleId = 5))
        val srv = listOf(server("a", 5))
        val first = TransactionHistoryReconciler.reconcile(input, srv)
        val second = TransactionHistoryReconciler.reconcile(input, srv)
        assertEquals(1, first.size)
        assertEquals(first, second)
    }
}
