package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.local.OfflineSyncStatus
import kotlin.reflect.KClass

/**
 * UIX-8C-05 (UIX8C-R146/R147/R148) — the pure projection from canonical checkout
 * and sync truth onto the truthful presentation [PaymentUiState], plus the
 * allowed-transition table for the payment/sync experience.
 *
 * This mapper is deliberately NOT a new state machine that decides transactions:
 * it only translates the authoritative states owned by [CashierViewModel] and the
 * offline sync queue into what the operator sees. Two invariants matter most:
 *  - a durable local save maps to [PaymentUiState.OfflineQueued]/Pending, NEVER
 *    to Synced (UIX8C-R147);
 *  - [PaymentUiState.Synced] is reachable ONLY from the canonical SYNCED sync
 *    status (a recorded server acknowledgement), NEVER optimistically (UIX8C-R148).
 *
 * Pure and framework-free so it is fully JVM-unit-testable without a device.
 */
object PaymentUiStateMapper {

    /** Project the canonical checkout state onto the presentation state. */
    fun fromCheckout(state: CashierViewModel.CheckoutState): PaymentUiState = when (state) {
        is CashierViewModel.CheckoutState.Idle -> PaymentUiState.Idle
        is CashierViewModel.CheckoutState.Submitting -> PaymentUiState.SubmittingOnline
        is CashierViewModel.CheckoutState.Success -> PaymentUiState.OnlineSuccess(state.sale)
        is CashierViewModel.CheckoutState.OfflineSaved -> PaymentUiState.OfflineQueued(
            clientReference = state.clientReference,
            grandTotal = state.grandTotal,
            change = state.change,
        )
        // A checkout-time error (canonical rejection / unsafe error) is terminal
        // for THIS online attempt and is never retryable as an offline queue item.
        is CashierViewModel.CheckoutState.Error -> PaymentUiState.Failed(state.message, retryable = false)
    }

    /**
     * Project a queued offline row's canonical sync status onto the presentation
     * state. [attempts] and [cap] decide whether a FAILED row is still on the
     * bounded-retry ladder (RetryScheduled) or a poison row (Failed, retryable so
     * the operator may force one governed manual retry). SYNCED requires the
     * canonical acknowledgement — it is never fabricated here (UIX8C-R148).
     */
    fun fromSyncStatus(status: String, attempts: Int, cap: Int): PaymentUiState = when (status) {
        OfflineSyncStatus.PENDING -> PaymentUiState.Pending
        OfflineSyncStatus.SYNCING -> PaymentUiState.Syncing
        OfflineSyncStatus.SYNCED -> PaymentUiState.Synced
        OfflineSyncStatus.FAILED ->
            if (attempts < cap) PaymentUiState.RetryScheduled
            else PaymentUiState.Failed("Sinkronisasi gagal berulang.", retryable = true)
        OfflineSyncStatus.CONFLICT -> PaymentUiState.Conflict(clientReference = "")
        // Fail-closed: an unknown status is never presented as success/synced.
        else -> PaymentUiState.Failed("Status tidak diketahui.", retryable = false)
    }

    /**
     * The allowed presentation transitions, matched by state CLASS (data-class
     * payloads are irrelevant to whether a transition is legal). Used to reject an
     * out-of-order UI update fail-closed (UIX8C-R146): a transition NOT in this
     * table must be ignored, never rendered. It mirrors the documented payment/sync
     * state machine and does not model canonical edges owned elsewhere.
     */
    fun isAllowedTransition(from: PaymentUiState, to: PaymentUiState): Boolean {
        if (from::class == to::class) return true // idempotent refresh of the same state
        return to::class in allowedNext(from::class)
    }

    private fun allowedNext(from: KClass<out PaymentUiState>): Set<KClass<out PaymentUiState>> = when (from) {
        PaymentUiState.Idle::class -> setOf(PaymentUiState.EditingTender::class)
        PaymentUiState.EditingTender::class -> setOf(PaymentUiState.Ready::class, PaymentUiState.Idle::class)
        PaymentUiState.Ready::class -> setOf(
            PaymentUiState.EditingTender::class,
            PaymentUiState.SubmittingOnline::class,
            PaymentUiState.PersistingOffline::class,
            PaymentUiState.Idle::class,
        )
        PaymentUiState.SubmittingOnline::class -> setOf(
            PaymentUiState.OnlineSuccess::class,
            PaymentUiState.PersistingOffline::class,
            PaymentUiState.Failed::class,
            PaymentUiState.Conflict::class,
        )
        PaymentUiState.PersistingOffline::class -> setOf(
            PaymentUiState.OfflineQueued::class,
            PaymentUiState.Failed::class,
        )
        PaymentUiState.OfflineQueued::class -> setOf(PaymentUiState.Pending::class, PaymentUiState.Idle::class)
        PaymentUiState.Pending::class -> setOf(
            PaymentUiState.Syncing::class,
            PaymentUiState.RetryScheduled::class,
            PaymentUiState.Failed::class,
        )
        PaymentUiState.Syncing::class -> setOf(
            PaymentUiState.Synced::class,
            PaymentUiState.RetryScheduled::class,
            PaymentUiState.Failed::class,
            PaymentUiState.Conflict::class,
        )
        PaymentUiState.RetryScheduled::class -> setOf(PaymentUiState.Syncing::class)
        PaymentUiState.Failed::class -> setOf(
            PaymentUiState.Syncing::class,
            PaymentUiState.RetryScheduled::class,
            PaymentUiState.Idle::class,
        )
        PaymentUiState.Conflict::class -> setOf(PaymentUiState.Idle::class) // explicit governed resolution
        PaymentUiState.OnlineSuccess::class -> setOf(PaymentUiState.Idle::class)
        PaymentUiState.Synced::class -> setOf(PaymentUiState.Idle::class)
        else -> emptySet()
    }
}
