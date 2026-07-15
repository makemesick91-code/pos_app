package com.aishtech.poslite.feature.history

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import kotlinx.coroutines.launch

/**
 * UIX-8C-06 — drives the premium transaction-history screen. It reads the
 * device's local transaction records (tenant/device scoped by the per-tenant Room
 * DB, UIX-7), normalizes them, and runs them through
 * [TransactionHistoryReconciler] so the list is deduplicated to exactly one row
 * per logical transaction (UIX8C-R181). Read-only: it never mutates a sale or
 * sync state.
 *
 * There is no server history-list endpoint on the device today, so the server
 * list is empty; the reconciler is nonetheless the enforced merge guard (a synced
 * local row and the same server-confirmed transaction would collapse to one row
 * keyed on the stable clientReference). This keeps the UI truthful and
 * duplicate-free the moment a server feed is introduced, without a second history
 * path.
 */
class TransactionHistoryViewModel(
    private val offline: OfflineSaleRepository,
) : ViewModel() {

    sealed class State {
        data object Loading : State()
        data class Loaded(val rows: List<HistoryRow>) : State()
        data object Empty : State()
        data class Error(val message: String) : State()
    }

    private val _state = MutableLiveData<State>(State.Loading)
    val state: LiveData<State> = _state

    fun load() {
        _state.value = State.Loading
        viewModelScope.launch {
            _state.value = try {
                val localRecords = offline.recentSales().map { it.toHistoryRecord() }
                val rows = TransactionHistoryReconciler.reconcile(localRecords)
                if (rows.isEmpty()) State.Empty else State.Loaded(rows)
            } catch (e: Exception) {
                State.Error(e.message.orEmpty())
            }
        }
    }

    private fun LocalOfflineSaleEntity.toHistoryRecord(): HistoryRecord = HistoryRecord(
        source = HistorySource.LOCAL,
        clientReference = clientReference,
        serverSaleId = serverSaleId,
        localId = localId,
        syncStatus = syncStatus,
        syncAttemptCount = syncAttemptCount,
        // Legacy Double bridged to whole rupiah at this single boundary (UIX8C-R179).
        grandTotal = RupiahMoney.fromDouble(grandTotal),
        reference = serverInvoiceNumber ?: clientReference,
        dateTime = saleDate,
        createdAt = createdAt,
    )
}
