package com.aishtech.poslite.feature.receipt

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.repository.ReceiptRepository
import com.aishtech.poslite.feature.printer.PrinterRepository
import kotlinx.coroutines.launch

/**
 * Drives the receipt screen (Sprint 6 foundation): load the backend-approved
 * receipt, render a text preview, and print via the Bluetooth foundation.
 *
 * Authoritative receipt content and print eligibility come from the backend; the
 * VM never recomputes totals and never prints a receipt the backend marked
 * non-printable.
 */
class ReceiptViewModel(
    private val receipts: ReceiptRepository,
    private val printer: PrinterRepository,
) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Ready(val receipt: ReceiptDto, val previewText: String) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>()
    val state: LiveData<UiState> = _state

    /** One-shot print feedback (success or failure message). */
    private val _printMessage = MutableLiveData<String?>()
    val printMessage: LiveData<String?> = _printMessage

    private var loaded: ReceiptDto? = null

    fun load(saleId: Long) {
        _state.value = UiState.Loading
        viewModelScope.launch {
            when (val result = receipts.getReceipt(saleId)) {
                is ResultState.Success -> {
                    loaded = result.data
                    _state.value = UiState.Ready(result.data, printer.preview(result.data))
                }
                is ResultState.Error -> _state.value = UiState.Error(result.message)
                ResultState.Loading -> _state.value = UiState.Loading
            }
        }
    }

    fun print() {
        val receipt = loaded ?: return
        viewModelScope.launch {
            _printMessage.value = when (val result = printer.printReceipt(receipt)) {
                is ResultState.Success -> "Struk terkirim ke printer."
                is ResultState.Error -> result.message
                ResultState.Loading -> null
            }
        }
    }

    fun consumePrintMessage() {
        _printMessage.value = null
    }
}
