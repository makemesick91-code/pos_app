package com.aishtech.poslite.feature.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.repository.AuthRepository
import kotlinx.coroutines.launch

class LoginViewModel(private val authRepository: AuthRepository) : ViewModel() {

    sealed class UiState {
        data object Idle : UiState()
        data object Loading : UiState()
        data object Success : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>(UiState.Idle)
    val state: LiveData<UiState> = _state

    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _state.value = UiState.Error("Email dan kata sandi wajib diisi.")
            return
        }
        _state.value = UiState.Loading
        viewModelScope.launch {
            _state.value = when (val result = authRepository.login(email, password)) {
                is ResultState.Success -> UiState.Success
                is ResultState.Error -> UiState.Error(result.message)
                ResultState.Loading -> UiState.Loading
            }
        }
    }
}
