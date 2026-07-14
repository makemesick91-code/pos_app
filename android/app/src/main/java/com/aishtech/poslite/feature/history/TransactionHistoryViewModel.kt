package com.aishtech.poslite.feature.history

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import kotlinx.coroutines.launch

/**
 * UIX-8B — drives the transaction-history screen. Reads the device's local sale
 * queue (tenant/device scoped by the per-tenant Room DB, UIX-7) and exposes a
 * single authoritative state with explicit loading / empty / error branches
 * (UIX8B-R003/R059/R060). Read-only: it never mutates sale or sync state.
 */
class TransactionHistoryViewModel(
    private val offline: OfflineSaleRepository,
) : ViewModel() {

    sealed class State {
        data object Loading : State()
        data class Loaded(val items: List<LocalOfflineSaleEntity>) : State()
        data object Empty : State()
        data class Error(val message: String) : State()
    }

    private val _state = MutableLiveData<State>(State.Loading)
    val state: LiveData<State> = _state

    fun load() {
        _state.value = State.Loading
        viewModelScope.launch {
            _state.value = try {
                val rows = offline.recentSales()
                if (rows.isEmpty()) State.Empty else State.Loaded(rows)
            } catch (e: Exception) {
                State.Error(e.message.orEmpty())
            }
        }
    }
}
