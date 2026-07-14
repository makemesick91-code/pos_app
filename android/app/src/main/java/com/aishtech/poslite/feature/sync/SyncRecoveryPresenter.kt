package com.aishtech.poslite.feature.sync

import com.aishtech.poslite.data.local.OfflineSyncStatus

/**
 * UIX-8C-05 (UIX8C-R157/R158/R159/R160) — the pure, governed decision of what the
 * offline-sync recovery UI may offer for a queued row, given its canonical sync
 * status, its attempt count, and the bounded-retry cap
 * ([com.aishtech.poslite.data.repository.OfflineSaleRepository.MAX_SYNC_ATTEMPTS]).
 *
 * It NEVER performs a sync, NEVER creates a transaction, and NEVER mutates the
 * queue — it only decides presentation and whether a SAFE manual retry is
 * offerable. A manual retry is offerable ONLY for a still-retryable FAILED row
 * (under the cap); a CONFLICT is never silently retried (UIX8C-R160), a poison row
 * at the cap is not offered auto-equivalent retry (bounded policy, UIX8C-R158),
 * and an in-flight SYNCING / already-SYNCED row is never re-triggered.
 *
 * Framework-free (label is an enum, not an Android resource) so it is fully
 * JVM-unit-testable; the UI maps [SyncRecoveryLabel] to a `@string` resource.
 */
object SyncRecoveryPresenter {

    /** Truthful, colour-independent label key for a queued row's sync status. */
    enum class SyncRecoveryLabel { PENDING, SYNCING, SYNCED, RETRY_SCHEDULED, FAILED, CONFLICT, UNKNOWN }

    /** The recovery presentation model for one queued offline row. */
    data class SyncRecoveryUi(
        val status: String,
        val label: SyncRecoveryLabel,
        /** True while the row can still make progress (auto or manual). */
        val isRetryable: Boolean,
        /** True only when a SAFE governed manual retry may be offered. */
        val showManualRetry: Boolean,
        /** True when no further automatic progress is possible without operator/support action. */
        val isTerminal: Boolean,
    )

    fun present(status: String, attempts: Int, cap: Int): SyncRecoveryUi = when (status) {
        OfflineSyncStatus.PENDING -> SyncRecoveryUi(
            status, SyncRecoveryLabel.PENDING, isRetryable = true, showManualRetry = false, isTerminal = false,
        )
        OfflineSyncStatus.SYNCING -> SyncRecoveryUi(
            status, SyncRecoveryLabel.SYNCING, isRetryable = true, showManualRetry = false, isTerminal = false,
        )
        OfflineSyncStatus.SYNCED -> SyncRecoveryUi(
            status, SyncRecoveryLabel.SYNCED, isRetryable = false, showManualRetry = false, isTerminal = true,
        )
        OfflineSyncStatus.FAILED -> {
            val underCap = attempts < cap
            SyncRecoveryUi(
                status = status,
                label = if (underCap) SyncRecoveryLabel.RETRY_SCHEDULED else SyncRecoveryLabel.FAILED,
                isRetryable = underCap,
                // A SAFE manual retry is offered only while the bounded ladder is
                // not exhausted; a poison row at the cap is not re-triggerable here.
                showManualRetry = underCap,
                isTerminal = !underCap,
            )
        }
        // A canonical conflict is NEVER silently retried (UIX8C-R160).
        OfflineSyncStatus.CONFLICT -> SyncRecoveryUi(
            status, SyncRecoveryLabel.CONFLICT, isRetryable = false, showManualRetry = false, isTerminal = true,
        )
        // Fail-closed: an unknown status is not retryable and not offered a retry.
        else -> SyncRecoveryUi(
            status, SyncRecoveryLabel.UNKNOWN, isRetryable = false, showManualRetry = false, isTerminal = true,
        )
    }

    /** Convenience predicate mirroring [present]'s [SyncRecoveryUi.showManualRetry]. */
    fun canManualRetry(status: String, attempts: Int, cap: Int): Boolean =
        status == OfflineSyncStatus.FAILED && attempts < cap
}
