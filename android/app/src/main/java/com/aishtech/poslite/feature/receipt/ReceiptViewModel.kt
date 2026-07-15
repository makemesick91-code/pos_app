package com.aishtech.poslite.feature.receipt

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.Event
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.feature.printer.PrintOutcome
import com.aishtech.poslite.feature.printer.PrinterCoordinator
import kotlinx.coroutines.launch

/**
 * UIX-8C-06 — drives the premium receipt / transaction-detail surface. The
 * receipt is a projection of one canonical transaction (UIX8C-R171): the
 * ViewModel binds to exactly one logical transaction, loads its truthful state,
 * and never recomputes money or mutates a transaction.
 *
 * Three governed entry points, one immutable projection type:
 *  - [loadServerSale] — an online-acknowledged sale (server sale id); the backend
 *    receipt is authoritative and printable when the backend approves it.
 *  - [loadLocalByReference] / [loadLocalById] — a durable local transaction
 *    (offline/pending, or synced) reopened from checkout or history. Values come
 *    from the persisted Room snapshot, never mutable cart state (UIX8C-R189). A
 *    pending draft is presented truthfully and cannot claim synchronization
 *    (UIX8C-R147/R175); a synced local row additionally fetches its backend
 *    receipt so a reprint is possible.
 *
 * Binding is identity-guarded: a load only publishes a projection whose identity
 * matches what was requested, and a projection loaded for a previous request can
 * never surface as the current one (UIX8C-R173/R190). Print feedback is a
 * one-shot [Event] so rotation/process recreation never replays it (UIX8C-R190).
 * Printing goes through the [PrinterCoordinator] and never alters transaction
 * authority (UIX8C-R191/R192/R193).
 */
class ReceiptViewModel(
    private val receipts: ServerReceiptSource,
    private val offline: LocalReceiptSource,
    private val coordinator: PrinterCoordinator,
) : ViewModel() {

    sealed class UiState {
        data object Loading : UiState()
        data class Ready(val projection: ReceiptProjection, val printable: Boolean) : UiState()
        data class Error(val message: String) : UiState()
    }

    private val _state = MutableLiveData<UiState>()
    val state: LiveData<UiState> = _state

    /** One-shot print outcome (typed). Consumed once; never replayed on rotation. */
    private val _printEvent = MutableLiveData<Event<PrintOutcome>>()
    val printEvent: LiveData<Event<PrintOutcome>> = _printEvent

    /** True while a print job is in flight (submit-guard for the UI). */
    private val _printing = MutableLiveData(false)
    val printing: LiveData<Boolean> = _printing

    /** The identity this ViewModel is currently bound to (stale-result guard). */
    private var boundIdentity: ReceiptIdentity? = null

    /** The backend-approved receipt used for printing; null when print is unavailable. */
    private var printableReceipt: ReceiptDto? = null

    fun loadServerSale(saleId: Long, clientReference: String? = null) {
        val requested = ReceiptIdentity(clientReference, saleId, null)
        beginLoad(requested)
        viewModelScope.launch {
            when (val result = receipts.getReceipt(saleId)) {
                is ResultState.Success -> {
                    val dto = result.data
                    publish(
                        requested,
                        ReceiptProjector.fromServerReceipt(dto, clientReference, synced = false),
                        dto.takeIf { it.printable },
                    )
                }
                is ResultState.Error -> fail(requested, result.message)
                ResultState.Loading -> Unit
            }
        }
    }

    fun loadLocalByReference(clientReference: String) {
        val requested = ReceiptIdentity(clientReference, null, null)
        beginLoad(requested)
        viewModelScope.launch {
            val found = offline.findSaleWithItemsByReference(clientReference)
            if (found == null) {
                fail(requested, "Transaksi tidak ditemukan.")
            } else {
                publishLocal(requested, found)
            }
        }
    }

    fun loadLocalById(localId: Long) {
        val requested = ReceiptIdentity(null, null, localId)
        beginLoad(requested)
        viewModelScope.launch {
            val found = offline.findSaleWithItems(localId)
            if (found == null) {
                fail(requested, "Transaksi tidak ditemukan.")
            } else {
                publishLocal(requested, found)
            }
        }
    }

    private suspend fun publishLocal(
        requested: ReceiptIdentity,
        found: OfflineSaleRepository.LocalSaleWithItems,
    ) {
        val projection = ReceiptProjector.fromLocalSale(found.sale, found.items)
        // A synced local row can be reprinted from its backend receipt; a pending
        // draft cannot (print stays disabled and truthful — UIX8C-R191).
        val printable = if (found.sale.syncStatus == OfflineSyncStatus.SYNCED &&
            found.sale.serverSaleId != null
        ) {
            (receipts.getReceipt(found.sale.serverSaleId) as? ResultState.Success)
                ?.data?.takeIf { it.printable }
        } else {
            null
        }
        publish(requested, projection, printable)
    }

    private fun beginLoad(requested: ReceiptIdentity) {
        boundIdentity = requested
        printableReceipt = null
        _state.value = UiState.Loading
    }

    private fun publish(
        requested: ReceiptIdentity,
        projection: ReceiptProjection,
        printable: ReceiptDto?,
    ) {
        // Stale-result guard: only publish for the request we are still bound to,
        // and only when the loaded projection is the same logical transaction.
        if (boundIdentity != requested) return
        if (!projection.identity.matches(requested)) return
        printableReceipt = printable
        _state.value = UiState.Ready(projection, printable != null)
    }

    private fun fail(requested: ReceiptIdentity, message: String) {
        if (boundIdentity != requested) return
        _state.value = UiState.Error(message)
    }

    /**
     * Print / reprint the current receipt through the coordinator. Reprint reuses
     * the same immutable backend-approved receipt and creates no new transaction
     * (UIX8C-R193). Concurrency is guarded by the coordinator (UIX8C-R198).
     */
    fun print() {
        val receipt = printableReceipt ?: return
        if (_printing.value == true) return
        _printing.value = true
        viewModelScope.launch {
            val outcome = coordinator.print(receipt)
            _printing.value = false
            _printEvent.value = Event(outcome)
        }
    }
}
