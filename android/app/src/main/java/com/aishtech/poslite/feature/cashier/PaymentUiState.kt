package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.remote.dto.SaleDto

/**
 * UIX-8C-05 (UIX8C-R146) — the truthful PRESENTATION state of the cash-payment /
 * offline-sync experience. This is a projection over the canonical checkout and
 * sync truth ([CashierViewModel.CheckoutState] and
 * [com.aishtech.poslite.data.local.OfflineSyncStatus]); it is NOT a second state
 * machine and holds NO transaction authority. Every value maps 1:1 to a distinct,
 * non-overlapping operator-facing state so the UI can never conflate, for
 * example, "queued locally" with "synchronized on the server" (UIX8C-R147).
 */
sealed class PaymentUiState {

    /** Nothing in progress; the sheet is closed or reset. */
    data object Idle : PaymentUiState()

    /** The operator is entering/adjusting the tender; not yet submittable. */
    data object EditingTender : PaymentUiState()

    /** A valid tender is entered; the confirm action is enabled. */
    data object Ready : PaymentUiState()

    /** An online CASH submit is in flight (awaiting server acknowledgement). */
    data object SubmittingOnline : PaymentUiState()

    /** A durable offline row is being committed to the local database. */
    data object PersistingOffline : PaymentUiState()

    /** Server acknowledged the sale (UIX8C-R144). */
    data class OnlineSuccess(val sale: SaleDto) : PaymentUiState()

    /** Durably saved locally, awaiting sync — NOT synchronized (UIX8C-R145/R147). */
    data class OfflineQueued(
        val clientReference: String,
        val grandTotal: Long,
        val change: Long,
    ) : PaymentUiState()

    /** Queued, not yet sent. */
    data object Pending : PaymentUiState()

    /** A sync attempt is in flight. */
    data object Syncing : PaymentUiState()

    /** A retry is scheduled (bounded backoff) and will run automatically. */
    data object RetryScheduled : PaymentUiState()

    /** Terminal-or-retryable failure. [retryable] gates the manual-retry action. */
    data class Failed(val message: String, val retryable: Boolean) : PaymentUiState()

    /** A canonical conflict needing explicit resolution — never silent success. */
    data class Conflict(val clientReference: String) : PaymentUiState()

    /** The backend acknowledgement is durably recorded locally (UIX8C-R148). */
    data object Synced : PaymentUiState()
}
