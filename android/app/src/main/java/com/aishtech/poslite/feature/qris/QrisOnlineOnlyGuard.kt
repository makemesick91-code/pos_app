package com.aishtech.poslite.feature.qris

import com.aishtech.poslite.core.network.NetworkMonitor

/**
 * Enforces the foundation rule that QRIS is online-only (Sprint 5 + Sprint 7):
 * a QRIS payment may never be created while the device is offline. CASH sales
 * are unaffected — they can be rung up offline and synced later.
 *
 * Pure and framework-free so it is unit-testable on the JVM.
 */
class QrisOnlineOnlyGuard(private val networkMonitor: NetworkMonitor) {

    /** True only when a QRIS payment may be created (i.e. the device is online). */
    fun canCreateQris(): Boolean = networkMonitor.isOnline()

    companion object {
        const val OFFLINE_MESSAGE = "QRIS membutuhkan koneksi internet"
    }
}
