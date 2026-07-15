package com.aishtech.poslite.core.session

/**
 * UIX-8C-07 — the unsynced-transaction protection gate (UIX8C-R229..R234).
 *
 * Logout, cashier switch, outlet switch, and tenant reset must NEVER silently
 * discard a transaction that has not been acknowledged by the server. This guard
 * counts EVERY durable transaction lacking a valid server ack — PENDING plus
 * bounded-retry FAILED (poison) rows — not just what is visible in the UI
 * (UIX8C-R231). A non-zero count blocks the destructive action and the UI then
 * surfaces the count, a "Sync sekarang" action, and a safe recovery path
 * (UIX8C-R232).
 */
sealed interface LogoutDecision {
    data object Allowed : LogoutDecision
    data class BlockedByUnsynced(val pending: Int, val failed: Int) : LogoutDecision {
        val total: Int get() = pending + failed
    }
}

/**
 * Minimal source of un-acknowledged counts so the guard is unit-testable without
 * the Room stack. Backed in production by [com.aishtech.poslite.data.repository.OfflineSaleRepository].
 */
interface UnsyncedCounter {
    suspend fun pendingCount(): Int
    suspend fun failedCount(): Int
}

class LogoutGuard(private val counter: UnsyncedCounter) {

    /** Evaluate whether a destructive session action may proceed. */
    suspend fun evaluate(): LogoutDecision {
        val pending = counter.pendingCount()
        val failed = counter.failedCount()
        return if (pending + failed > 0) {
            LogoutDecision.BlockedByUnsynced(pending = pending, failed = failed)
        } else {
            LogoutDecision.Allowed
        }
    }
}
