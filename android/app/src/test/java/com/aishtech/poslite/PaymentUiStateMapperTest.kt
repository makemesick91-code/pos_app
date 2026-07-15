package com.aishtech.poslite

import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.feature.cashier.CashierViewModel
import com.aishtech.poslite.feature.cashier.PaymentUiState
import com.aishtech.poslite.feature.cashier.PaymentUiStateMapper
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-05 (UIX8C-R146/R147/R148) — the pure projection from canonical checkout
 * and sync truth onto the truthful presentation state, plus the allowed-transition
 * table. The critical invariants: a durable local save projects to OfflineQueued
 * (never Synced), and Synced is reachable ONLY from a canonical SYNCED status.
 */
class PaymentUiStateMapperTest {

    private fun sale() = sampleSale()

    @Test
    fun checkoutStatesProjectDistinctly() {
        assertEquals(PaymentUiState.Idle, PaymentUiStateMapper.fromCheckout(CashierViewModel.CheckoutState.Idle))
        assertEquals(
            PaymentUiState.SubmittingOnline,
            PaymentUiStateMapper.fromCheckout(CashierViewModel.CheckoutState.Submitting),
        )
        assertTrue(
            PaymentUiStateMapper.fromCheckout(CashierViewModel.CheckoutState.Success(sale()))
                is PaymentUiState.OnlineSuccess,
        )
    }

    @Test
    fun durableOfflineSaveProjectsToQueuedNeverSynced() {
        val state = PaymentUiStateMapper.fromCheckout(
            CashierViewModel.CheckoutState.OfflineSaved(clientReference = "ref-1", grandTotal = 25_000L, change = 5_000L),
        )
        assertTrue(state is PaymentUiState.OfflineQueued)
        state as PaymentUiState.OfflineQueued
        assertEquals("ref-1", state.clientReference)
        assertEquals(25_000L, state.grandTotal)
        // The one thing it must never become:
        assertFalse(state is PaymentUiState.Synced)
    }

    @Test
    fun checkoutErrorIsNonRetryableFailure() {
        val state = PaymentUiStateMapper.fromCheckout(CashierViewModel.CheckoutState.Error("HTTP 422"))
        assertTrue(state is PaymentUiState.Failed)
        assertFalse((state as PaymentUiState.Failed).retryable)
    }

    @Test
    fun syncStatusProjectionIsTruthfulAndDistinct() {
        val cap = 5
        assertEquals(PaymentUiState.Pending, PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.PENDING, 0, cap))
        assertEquals(PaymentUiState.Syncing, PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.SYNCING, 1, cap))
        assertEquals(PaymentUiState.Synced, PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.SYNCED, 1, cap))
        // FAILED under the cap is on the retry ladder; at the cap it is a poison row.
        assertEquals(
            PaymentUiState.RetryScheduled,
            PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.FAILED, 2, cap),
        )
        val poison = PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.FAILED, cap, cap)
        assertTrue(poison is PaymentUiState.Failed && poison.retryable)
        assertTrue(PaymentUiStateMapper.fromSyncStatus(OfflineSyncStatus.CONFLICT, 0, cap) is PaymentUiState.Conflict)
    }

    @Test
    fun syncedIsNeverReachedFromANonSyncedStatus() {
        val cap = 5
        for (status in listOf(OfflineSyncStatus.PENDING, OfflineSyncStatus.SYNCING, OfflineSyncStatus.FAILED, OfflineSyncStatus.CONFLICT)) {
            assertFalse(PaymentUiStateMapper.fromSyncStatus(status, 0, cap) is PaymentUiState.Synced)
        }
    }

    @Test
    fun unknownStatusFailsClosedNeverSuccess() {
        val state = PaymentUiStateMapper.fromSyncStatus("WEIRD", 0, 5)
        assertTrue(state is PaymentUiState.Failed)
        assertFalse(state is PaymentUiState.Synced)
    }

    @Test
    fun allowedTransitionsHold() {
        assertTrue(
            PaymentUiStateMapper.isAllowedTransition(
                PaymentUiState.SubmittingOnline, PaymentUiState.OnlineSuccess(sale()),
            ),
        )
        assertTrue(
            PaymentUiStateMapper.isAllowedTransition(
                PaymentUiState.SubmittingOnline, PaymentUiState.PersistingOffline,
            ),
        )
        assertTrue(
            PaymentUiStateMapper.isAllowedTransition(PaymentUiState.Syncing, PaymentUiState.Synced),
        )
        // Idempotent same-state refresh is always allowed.
        assertTrue(PaymentUiStateMapper.isAllowedTransition(PaymentUiState.Pending, PaymentUiState.Pending))
    }

    @Test
    fun invalidTransitionsFailClosed() {
        // You can never jump straight from Idle to Synced, or from Pending to
        // OnlineSuccess — the table rejects it (UIX8C-R146).
        assertFalse(PaymentUiStateMapper.isAllowedTransition(PaymentUiState.Idle, PaymentUiState.Synced))
        assertFalse(
            PaymentUiStateMapper.isAllowedTransition(PaymentUiState.Pending, PaymentUiState.OnlineSuccess(sale())),
        )
        assertFalse(PaymentUiStateMapper.isAllowedTransition(PaymentUiState.OfflineQueued("", 0, 0), PaymentUiState.Synced))
    }
}
