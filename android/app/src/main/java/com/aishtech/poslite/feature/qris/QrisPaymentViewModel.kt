package com.aishtech.poslite.feature.qris

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.QrisPaymentDto
import com.aishtech.poslite.data.repository.QrisRepository
import kotlinx.coroutines.launch

/**
 * Drives the QRIS payment screen (Sprint 5 foundation): create a QRIS payment
 * for a sale, display its QR payload/status, and refresh the status on demand.
 * All gateway work lives in the backend; this VM only consumes backend endpoints.
 */
class QrisPaymentViewModel(private val qris: QrisRepository) : ViewModel() {

    sealed class UiState {
        data object Idle : UiState()
        data object Loading : UiState()
        data class Ready(val payment: QrisPaymentDto) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>(UiState.Idle)
    val state: LiveData<UiState> = _state

    private var paymentId: Long? = null

    /** Create (or reuse) a QRIS payment for the given sale. */
    fun start(saleId: Long, provider: String? = null) {
        _state.value = UiState.Loading
        viewModelScope.launch {
            when (val result = qris.createQrisPayment(saleId, provider)) {
                is ResultState.Success -> {
                    paymentId = result.data.id
                    _state.value = UiState.Ready(result.data)
                }
                is ResultState.Error -> _state.value = UiState.Error(result.message)
                ResultState.Loading -> _state.value = UiState.Loading
            }
        }
    }

    /** Poll the backend for the latest payment status. */
    fun refreshStatus() {
        val id = paymentId ?: return
        _state.value = UiState.Loading
        viewModelScope.launch {
            when (val result = qris.getPaymentStatus(id)) {
                is ResultState.Success -> _state.value = UiState.Ready(result.data)
                is ResultState.Error -> _state.value = UiState.Error(result.message)
                ResultState.Loading -> _state.value = UiState.Loading
            }
        }
    }
}
