package com.aishtech.poslite

import com.aishtech.poslite.R
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.feature.history.SyncStatusDisplay
import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * UIX-8B — each offline-sale sync status maps to a distinct TEXT label (never
 * colour alone, UIX8B-R060/R064); an unrecognised status degrades to an explicit
 * "unknown" label rather than a fabricated success.
 */
class SyncStatusDisplayTest {

    @Test
    fun eachStatusHasItsOwnLabel() {
        assertEquals(R.string.hist_status_pending, SyncStatusDisplay.badge(OfflineSyncStatus.PENDING).labelRes)
        assertEquals(R.string.hist_status_syncing, SyncStatusDisplay.badge(OfflineSyncStatus.SYNCING).labelRes)
        assertEquals(R.string.hist_status_synced, SyncStatusDisplay.badge(OfflineSyncStatus.SYNCED).labelRes)
        assertEquals(R.string.hist_status_failed, SyncStatusDisplay.badge(OfflineSyncStatus.FAILED).labelRes)
        assertEquals(R.string.hist_status_conflict, SyncStatusDisplay.badge(OfflineSyncStatus.CONFLICT).labelRes)
    }

    @Test
    fun unknownStatusIsNotPresentedAsSynced() {
        val badge = SyncStatusDisplay.badge("SOMETHING_ELSE")
        assertEquals(R.string.hist_status_unknown, badge.labelRes)
    }
}
