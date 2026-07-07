package com.aishtech.poslite.feature.subscription

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.repository.SubscriptionRepository
import kotlinx.coroutines.launch

/**
 * Drives the lightweight subscription/device status screen (Sprint 10). It only
 * displays the backend-computed status — no billing, upgrade, or Play Billing UI.
 */
class SubscriptionStatusViewModel(
    private val subscriptions: SubscriptionRepository,
) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Ready(val model: SubscriptionStatusDisplay.UiModel) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>()
    val state: LiveData<UiState> = _state

    fun refresh() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            _state.value = when (val result = subscriptions.getStatus()) {
                is ResultState.Success -> UiState.Ready(SubscriptionStatusDisplay.map(result.data))
                is ResultState.Error -> UiState.Error(result.message)
                ResultState.Loading -> UiState.Loading
            }
        }
    }
}
