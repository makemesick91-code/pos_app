package com.aishtech.poslite.data.local

/**
 * Lifecycle of a locally-stored offline CASH sale (Sprint 7).
 *
 * PENDING  → queued, not yet sent.
 * SYNCING  → an attempt is in flight.
 * SYNCED   → the backend accepted it (or acknowledged an idempotent replay).
 * FAILED   → a transient error (network/server); safe to retry.
 * CONFLICT → a permanent rejection (validation/idempotency); needs resolution.
 */
object OfflineSyncStatus {
    const val PENDING = "PENDING"
    const val SYNCING = "SYNCING"
    const val SYNCED = "SYNCED"
    const val FAILED = "FAILED"
    const val CONFLICT = "CONFLICT"
}
