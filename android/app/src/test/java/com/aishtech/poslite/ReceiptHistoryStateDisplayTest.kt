package com.aishtech.poslite

import com.aishtech.poslite.R
import com.aishtech.poslite.feature.history.HistoryDisplayState
import com.aishtech.poslite.feature.history.HistoryStateDisplay
import com.aishtech.poslite.feature.receipt.ReceiptStateDisplay
import com.aishtech.poslite.feature.receipt.ReceiptTransactionState
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-06 — receipt/history state is conveyed by a distinct TEXT label, never
 * colour alone (UIX8C-R205), and each state has its own label.
 */
class ReceiptHistoryStateDisplayTest {

    @Test fun receiptStates_haveDistinctLabels() {
        val labels = ReceiptTransactionState.values().map { ReceiptStateDisplay.badge(it).labelRes }
        assertEquals("every receipt state has its own label", labels.size, labels.toSet().size)
    }

    @Test fun receipt_offlinePending_isNotShownAsSuccessColour() {
        val pending = ReceiptStateDisplay.badge(ReceiptTransactionState.OFFLINE_PENDING)
        val success = ReceiptStateDisplay.badge(ReceiptTransactionState.ONLINE_SUCCESS)
        assertNotEquals(pending.colorRes, success.colorRes)
        assertEquals(R.color.status_warning_fg, pending.colorRes)
    }

    @Test fun historyStates_haveDistinctLabels() {
        val labels = HistoryDisplayState.values().map { HistoryStateDisplay.badge(it).labelRes }
        assertEquals("every history state has its own label", labels.size, labels.toSet().size)
    }

    @Test fun history_retryScheduled_and_conflict_areDistinctFromFailed() {
        val failed = HistoryStateDisplay.badge(HistoryDisplayState.FAILED).labelRes
        val retry = HistoryStateDisplay.badge(HistoryDisplayState.RETRY_SCHEDULED).labelRes
        val conflict = HistoryStateDisplay.badge(HistoryDisplayState.CONFLICT).labelRes
        assertNotEquals(failed, retry)
        assertNotEquals(failed, conflict)
    }

    @Test fun history_syncedUsesSuccess_unknownNeverSynced() {
        assertEquals(R.color.status_success_fg, HistoryStateDisplay.badge(HistoryDisplayState.SYNCED).colorRes)
        assertTrue(
            "unknown must not be presented as synced",
            HistoryStateDisplay.badge(HistoryDisplayState.UNKNOWN).labelRes != R.string.hist_status_synced,
        )
    }
}
