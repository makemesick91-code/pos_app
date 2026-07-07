package com.aishtech.poslite.feature.reports

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.DailySalesReportDto
import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryItemDto
import com.aishtech.poslite.data.remote.dto.PaymentSummaryItemDto
import com.aishtech.poslite.data.repository.ClosingRepository
import com.aishtech.poslite.data.repository.ReportRepository
import kotlinx.coroutines.launch
import java.time.LocalDate

/**
 * Drives the lightweight daily summary screen (Sprint 9). It fetches the
 * backend-computed daily sales, payment, and inventory summaries and requests a
 * daily close. It never recomputes an authoritative total — all figures come
 * from the backend.
 */
class ReportsViewModel(
    private val reports: ReportRepository,
    private val closings: ClosingRepository,
) : ViewModel() {

    data class Summary(
        val sales: DailySalesReportDto,
        val payments: List<PaymentSummaryItemDto>,
        val inventory: List<InventoryMovementSummaryItemDto>,
    )

    sealed class UiState {
        data object Loading : UiState()
        data class Ready(val summary: Summary) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>()
    val state: LiveData<UiState> = _state

    /** One-shot closing feedback (success/replay/failure message). */
    private val _closingMessage = MutableLiveData<String?>()
    val closingMessage: LiveData<String?> = _closingMessage

    private val _closing = MutableLiveData<Boolean>(false)
    val closing: LiveData<Boolean> = _closing

    val businessDate: String = LocalDate.now().toString()

    fun refresh() {
        _state.value = UiState.Loading
        viewModelScope.launch {
            when (val sales = reports.getDailySales()) {
                is ResultState.Success -> loadRest(sales.data)
                is ResultState.Error -> _state.value = UiState.Error(sales.message)
                ResultState.Loading -> _state.value = UiState.Loading
            }
        }
    }

    private suspend fun loadRest(sales: DailySalesReportDto) {
        val payments = when (val r = reports.getPaymentSummary()) {
            is ResultState.Success -> r.data
            else -> emptyList()
        }
        val inventory = when (val r = reports.getInventoryMovementsSummary()) {
            is ResultState.Success -> r.data
            else -> emptyList()
        }
        _state.value = UiState.Ready(Summary(sales, payments, inventory))
    }

    fun closeToday() {
        if (_closing.value == true) return
        _closing.value = true
        viewModelScope.launch {
            when (val result = closings.createClosing(storeId = null, businessDate = businessDate)) {
                is ResultState.Success -> {
                    _closingMessage.value = ReportDisplay.closingMessage(result.data.duplicateReplay)
                    refresh()
                }
                is ResultState.Error -> _closingMessage.value = result.message
                ResultState.Loading -> Unit
            }
            _closing.value = false
        }
    }

    fun consumeClosingMessage() {
        _closingMessage.value = null
    }
}
