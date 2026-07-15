package com.aishtech.poslite.feature.activation

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.runtime.ActivationStateStore
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.repository.DeviceActivationRepository
import kotlinx.coroutines.launch

/**
 * UIX-8C-07 — the device-activation submission ViewModel (UIX8C-R217). It holds a
 * single authoritative [state], guards against double-submit while a request is
 * in flight, and marks the installation activated ONLY on a server-confirmed
 * success (validity stays server-authoritative). The raw code is never retained
 * after submission and never logged.
 */
class DeviceActivationViewModel(
    private val repository: DeviceActivationRepository,
    private val activationState: ActivationStateStore,
) : ViewModel() {

    sealed interface State {
        data object Idle : State
        data object Submitting : State
        data class Activated(val tenantHint: String?) : State
        data class Rejected(val message: String) : State
    }

    private val _state = MutableLiveData<State>(State.Idle)
    val state: LiveData<State> = _state

    private var inFlight = false

    fun activate(rawCode: String) {
        // ViewModel-level double-submit guard (not UI-only).
        if (inFlight) return
        val code = rawCode.trim()
        if (code.length < MIN_CODE_LENGTH) {
            _state.value = State.Rejected("Masukkan kode aktivasi yang valid.")
            return
        }
        inFlight = true
        _state.value = State.Submitting
        viewModelScope.launch {
            val result = repository.activate(code)
            inFlight = false
            _state.value = when (result) {
                is ResultState.Success -> {
                    activationState.markActivated()
                    State.Activated(result.data.deviceLabel)
                }
                is ResultState.Error -> State.Rejected(result.message)
                ResultState.Loading -> State.Submitting
            }
        }
    }

    fun resetError() {
        if (_state.value is State.Rejected) _state.value = State.Idle
    }

    private companion object {
        const val MIN_CODE_LENGTH = 8
    }
}
