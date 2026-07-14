package com.aishtech.poslite.feature.history

import androidx.annotation.ColorRes
import androidx.annotation.StringRes
import com.aishtech.poslite.R
import com.aishtech.poslite.data.local.OfflineSyncStatus

/**
 * UIX-8B — maps an offline-sale sync status to an accessible history badge
 * (UIX8B-R060/R064). Every status carries a TEXT label (never colour alone); the
 * colour is a secondary cue. Pure and side-effect-free so it is unit-testable.
 */
object SyncStatusDisplay {

    data class Badge(@StringRes val labelRes: Int, @ColorRes val colorRes: Int)

    fun badge(status: String): Badge = when (status) {
        OfflineSyncStatus.PENDING -> Badge(R.string.hist_status_pending, R.color.status_warning_fg)
        OfflineSyncStatus.SYNCING -> Badge(R.string.hist_status_syncing, R.color.status_info_fg)
        OfflineSyncStatus.SYNCED -> Badge(R.string.hist_status_synced, R.color.status_success_fg)
        OfflineSyncStatus.FAILED -> Badge(R.string.hist_status_failed, R.color.status_danger_fg)
        OfflineSyncStatus.CONFLICT -> Badge(R.string.hist_status_conflict, R.color.status_danger_fg)
        else -> Badge(R.string.hist_status_unknown, R.color.text_secondary)
    }
}
