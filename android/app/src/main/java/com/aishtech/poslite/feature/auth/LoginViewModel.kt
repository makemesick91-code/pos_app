package com.aishtech.poslite.feature.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.Event
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.DeviceRepository
import com.aishtech.poslite.data.repository.SubscriptionRepository
import com.aishtech.poslite.feature.subscription.SubscriptionStatusDisplay
import kotlinx.coroutines.launch

/**
 * Login + Sprint 10 session bootstrap. After a successful auth the flow:
 *   1. ensures a local device UUID exists (via device registration),
 *   2. reads the backend-computed subscription status,
 *   3. registers this device if the subscription is allowed,
 *   4. emits Success only when both the subscription and the device are OK.
 *
 * A blocked subscription or a rejected device (e.g. limit reached) yields
 * Blocked — the cashier is never entered. A network error yields Error and never
 * fakes an allowed state.
 */
class LoginViewModel(
    private val authRepository: AuthRepository,
    private val subscriptionRepository: SubscriptionRepository,
    private val deviceRepository: DeviceRepository,
) : ViewModel() {

    sealed class UiState {
        data object Idle : UiState()
        data object Loading : UiState()
        data class Blocked(val message: String) : UiState()
        data class Error(val message: String) : UiState()
    }

    /**
     * UIX8B-R008 — navigation is a one-time event, not sticky state. Delivered
     * through [Event] so a rotation/process-recreation that re-observes the last
     * value does NOT re-launch the destination.
     */
    enum class Nav { CASHIER, SUBSCRIPTION }

    private val _state = MutableLiveData<UiState>(UiState.Idle)
    val state: LiveData<UiState> = _state

    private val _nav = MutableLiveData<Event<Nav>>()
    val nav: LiveData<Event<Nav>> = _nav

    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _state.value = UiState.Error("Email dan kata sandi wajib diisi.")
            return
        }
        _state.value = UiState.Loading
        viewModelScope.launch {
            when (val result = authRepository.login(email, password)) {
                is ResultState.Success -> bootstrapSession()
                is ResultState.Error -> _state.value = UiState.Error(result.message)
                ResultState.Loading -> _state.value = UiState.Loading
            }
        }
    }

    private suspend fun bootstrapSession() {
        when (val status = subscriptionRepository.getStatus()) {
            is ResultState.Success -> {
                if (!SubscriptionStatusDisplay.isAllowed(status.data)) {
                    _state.value = UiState.Blocked(SubscriptionStatusDisplay.blockedReason(status.data))
                    _nav.value = Event(Nav.SUBSCRIPTION)
                    return
                }
                registerDevice()
            }
            is ResultState.Error -> _state.value = UiState.Error(status.message)
            ResultState.Loading -> _state.value = UiState.Loading
        }
    }

    private suspend fun registerDevice() {
        when (deviceRepository.registerCurrentDevice()) {
            is ResultState.Success -> {
                _state.value = UiState.Idle
                _nav.value = Event(Nav.CASHIER)
            }
            is ResultState.Error ->
                _state.value = UiState.Blocked("Perangkat tidak dapat diaktifkan. Periksa batas perangkat langganan.")
            ResultState.Loading -> _state.value = UiState.Loading
        }
    }
}
