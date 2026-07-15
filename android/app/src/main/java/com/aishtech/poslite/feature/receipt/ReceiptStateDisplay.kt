package com.aishtech.poslite.feature.receipt

import androidx.annotation.ColorRes
import androidx.annotation.StringRes
import com.aishtech.poslite.R

/**
 * UIX-8C-06 — maps a [ReceiptTransactionState] to an accessible receipt-state
 * badge. Every state carries a TEXT label (never colour alone — UIX8C-R205); the
 * colour is a secondary cue. Pure and side-effect-free so it is unit-testable on
 * the JVM.
 */
object ReceiptStateDisplay {

    data class Badge(@StringRes val labelRes: Int, @ColorRes val colorRes: Int)

    fun badge(state: ReceiptTransactionState): Badge = when (state) {
        ReceiptTransactionState.ONLINE_SUCCESS ->
            Badge(R.string.receipt_state_online_success, R.color.status_success_fg)
        ReceiptTransactionState.SYNCED ->
            Badge(R.string.receipt_state_synced, R.color.status_success_fg)
        ReceiptTransactionState.OFFLINE_PENDING ->
            Badge(R.string.receipt_state_offline_pending, R.color.status_warning_fg)
        ReceiptTransactionState.SYNCING ->
            Badge(R.string.receipt_state_syncing, R.color.status_info_fg)
        ReceiptTransactionState.FAILED ->
            Badge(R.string.receipt_state_failed, R.color.status_danger_fg)
        ReceiptTransactionState.CONFLICT ->
            Badge(R.string.receipt_state_conflict, R.color.status_danger_fg)
    }
}
