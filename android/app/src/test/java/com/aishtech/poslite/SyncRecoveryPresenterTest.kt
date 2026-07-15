package com.aishtech.poslite

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.sync.SyncRecoveryPresenter
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-05 (UIX8C-R157/R158/R159/R160) — the governed sync-recovery decision. A
 * SAFE manual retry is offered ONLY for a still-retryable FAILED row (under the
 * bounded cap); a CONFLICT is never silently retried; a poison row at the cap and
 * an in-flight/SYNCED row are never re-triggered.
 */
class SyncRecoveryPresenterTest {

    private val cap = OfflineSaleRepository.MAX_SYNC_ATTEMPTS

    @Test
    fun failedUnderCapIsRetryableAndOffersManualRetry() {
        val ui = SyncRecoveryPresenter.present(OfflineSyncStatus.FAILED, attempts = 1, cap = cap)
        assertEquals(SyncRecoveryPresenter.SyncRecoveryLabel.RETRY_SCHEDULED, ui.label)
        assertTrue(ui.isRetryable)
        assertTrue(ui.showManualRetry)
        assertFalse(ui.isTerminal)
        assertTrue(SyncRecoveryPresenter.canManualRetry(OfflineSyncStatus.FAILED, 1, cap))
    }

    @Test
    fun poisonRowAtCapIsTerminalAndNotAutoRetried() {
        val ui = SyncRecoveryPresenter.present(OfflineSyncStatus.FAILED, attempts = cap, cap = cap)
        assertEquals(SyncRecoveryPresenter.SyncRecoveryLabel.FAILED, ui.label)
        assertFalse(ui.isRetryable)
        assertFalse(ui.showManualRetry)
        assertTrue(ui.isTerminal)
        assertFalse(SyncRecoveryPresenter.canManualRetry(OfflineSyncStatus.FAILED, cap, cap))
    }

    @Test
    fun conflictIsNeverRetried() {
        val ui = SyncRecoveryPresenter.present(OfflineSyncStatus.CONFLICT, attempts = 0, cap = cap)
        assertEquals(SyncRecoveryPresenter.SyncRecoveryLabel.CONFLICT, ui.label)
        assertFalse(ui.showManualRetry)
        assertTrue(ui.isTerminal)
        assertFalse(SyncRecoveryPresenter.canManualRetry(OfflineSyncStatus.CONFLICT, 0, cap))
    }

    @Test
    fun pendingAndSyncingNeverOfferManualRetry() {
        assertFalse(SyncRecoveryPresenter.present(OfflineSyncStatus.PENDING, 0, cap).showManualRetry)
        assertFalse(SyncRecoveryPresenter.present(OfflineSyncStatus.SYNCING, 0, cap).showManualRetry)
        assertFalse(SyncRecoveryPresenter.canManualRetry(OfflineSyncStatus.PENDING, 0, cap))
        assertFalse(SyncRecoveryPresenter.canManualRetry(OfflineSyncStatus.SYNCING, 0, cap))
    }

    @Test
    fun syncedIsTerminalSuccessNoRetry() {
        val ui = SyncRecoveryPresenter.present(OfflineSyncStatus.SYNCED, attempts = 1, cap = cap)
        assertEquals(SyncRecoveryPresenter.SyncRecoveryLabel.SYNCED, ui.label)
        assertTrue(ui.isTerminal)
        assertFalse(ui.showManualRetry)
    }

    @Test
    fun unknownStatusFailsClosed() {
        val ui = SyncRecoveryPresenter.present("MYSTERY", 0, cap)
        assertEquals(SyncRecoveryPresenter.SyncRecoveryLabel.UNKNOWN, ui.label)
        assertFalse(ui.isRetryable)
        assertFalse(ui.showManualRetry)
    }
}
