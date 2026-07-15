package com.aishtech.poslite.feature.history

import androidx.annotation.ColorRes
import androidx.annotation.StringRes
import com.aishtech.poslite.R

/**
 * UIX-8C-06 — maps a reconciled [HistoryDisplayState] to an accessible history
 * badge. Every state carries a distinct TEXT label (never colour alone —
 * UIX8C-R205); RETRY_SCHEDULED and CONFLICT are visually and textually distinct
 * from a terminal FAILED. Pure and unit-testable.
 */
object HistoryStateDisplay {

    data class Badge(@StringRes val labelRes: Int, @ColorRes val colorRes: Int)

    fun badge(state: HistoryDisplayState): Badge = when (state) {
        HistoryDisplayState.PENDING ->
            Badge(R.string.hist_status_pending, R.color.status_warning_fg)
        HistoryDisplayState.SYNCING ->
            Badge(R.string.hist_status_syncing, R.color.status_info_fg)
        HistoryDisplayState.RETRY_SCHEDULED ->
            Badge(R.string.hist_status_retry_scheduled, R.color.status_warning_fg)
        HistoryDisplayState.SYNCED ->
            Badge(R.string.hist_status_synced, R.color.status_success_fg)
        HistoryDisplayState.FAILED ->
            Badge(R.string.hist_status_failed, R.color.status_danger_fg)
        HistoryDisplayState.CONFLICT ->
            Badge(R.string.hist_status_conflict, R.color.status_danger_fg)
        HistoryDisplayState.UNKNOWN ->
            Badge(R.string.hist_status_unknown, R.color.text_secondary)
    }
}
