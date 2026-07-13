package com.aishtech.poslite.feature.cashier

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.remote.dto.SaleDto
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.data.repository.StockRepository
import com.aishtech.poslite.feature.sync.CatalogSyncManager
import kotlinx.coroutines.launch

/**
 * Drives the cashier screen: local product search, cash-first cart, manual
 * catalog sync, online CASH checkout (Sprint 4), and — since Sprint 7 — offline
 * CASH checkout with a local sync queue and a manual "sync now" action.
 */
class CashierViewModel(
    private val catalogRepository: CatalogRepository,
    private val syncManager: CatalogSyncManager,
    private val sales: SalesRepository,
    private val cart: CartRepository,
    private val offline: OfflineSaleRepository,
    private val stock: StockRepository,
) : ViewModel() {

    /** UI state for the checkout flow. */
    sealed class CheckoutState {
        data object Idle : CheckoutState()
        data object Submitting : CheckoutState()
        data class Success(val sale: SaleDto) : CheckoutState()
        // Sprint 7 — the sale is stored locally as an OFFLINE DRAFT (not synced).
        data class OfflineSaved(val clientReference: String, val grandTotal: Double, val change: Double) : CheckoutState()
        data class Error(val message: String) : CheckoutState()
    }

    /** Summary of the local offline sync queue shown to the cashier. */
    data class SyncCounts(val pending: Int, val failed: Int)

    private val _products = MutableLiveData<List<LocalProductEntity>>(emptyList())
    val products: LiveData<List<LocalProductEntity>> = _products

    private val _cartItems = MutableLiveData<List<CartItem>>(emptyList())
    val cartItems: LiveData<List<CartItem>> = _cartItems

    private val _subtotal = MutableLiveData(0.0)
    val subtotal: LiveData<Double> = _subtotal

    private val _syncStatus = MutableLiveData("Belum disinkronkan")
    val syncStatus: LiveData<String> = _syncStatus

    private val _syncing = MutableLiveData(false)
    val syncing: LiveData<Boolean> = _syncing

    private val _checkout = MutableLiveData<CheckoutState>(CheckoutState.Idle)
    val checkout: LiveData<CheckoutState> = _checkout

    private val _syncCounts = MutableLiveData(SyncCounts(pending = 0, failed = 0))
    val syncCounts: LiveData<SyncCounts> = _syncCounts

    private val _offlineSyncing = MutableLiveData(false)
    val offlineSyncing: LiveData<Boolean> = _offlineSyncing

    // Sprint 8 — productId -> backend current_stock string for informational
    // stock labels. Empty until a fetch resolves; the UI shows "Stok: -".
    private val _stockLabels = MutableLiveData<Map<Long, String>>(emptyMap())
    val stockLabels: LiveData<Map<Long, String>> = _stockLabels

    fun search(query: String) {
        viewModelScope.launch {
            _products.value = catalogRepository.search(query)
        }
    }

    /**
     * Fetch current stock from the backend for the informational cashier labels.
     * Best-effort and non-blocking: a failure leaves labels as "-" and never
     * affects checkout. The backend remains the stock authority.
     */
    fun refreshStock() {
        viewModelScope.launch {
            when (val result = stock.getCurrentStock()) {
                is ResultState.Success -> {
                    _stockLabels.value = result.data
                        .filter { it.currentStock != null }
                        .associate { it.productId to (it.currentStock ?: "") }
                }
                // Keep existing labels on error; stock is only informational.
                is ResultState.Error -> Unit
                ResultState.Loading -> Unit
            }
        }
    }

    fun sync() {
        _syncing.value = true
        _syncStatus.value = "Menyinkronkan…"
        viewModelScope.launch {
            when (val result = syncManager.sync()) {
                is ResultState.Success -> {
                    _syncStatus.value =
                        "Tersinkron: ${result.data.products} produk, ${result.data.categories} kategori"
                    search("")
                    refreshStock()
                }
                is ResultState.Error -> _syncStatus.value = result.message
                ResultState.Loading -> Unit
            }
            _syncing.value = false
        }
    }

    fun addToCart(product: LocalProductEntity) {
        cart.addProduct(product.id, product.name, product.effectiveSellingPrice)
        emitCart()
    }

    fun updateQuantity(productId: Long, quantity: Int) {
        cart.updateQuantity(productId, quantity)
        emitCart()
    }

    fun removeItem(productId: Long) {
        cart.removeProduct(productId)
        emitCart()
    }

    fun clearCart() {
        cart.clear()
        emitCart()
    }

    /**
     * Submit the cart as an online CASH sale. The cart is cleared ONLY after the
     * backend confirms the sale; on any failure the cart is kept intact so the
     * cashier can retry (Sprint 4 runtime rule).
     */
    fun checkoutCash(paidAmount: Double) {
        // UIX7-R015/R025 — a submission is already in flight; ignore the repeat
        // tap so a double-press can never create two server transactions. The UI
        // also disables the button, but this guard closes the tap-before-observer
        // race at the source.
        if (_checkout.value is CheckoutState.Submitting) return
        if (cart.isEmpty()) {
            _checkout.value = CheckoutState.Error("Keranjang kosong.")
            return
        }
        if (paidAmount < cart.subtotal()) {
            _checkout.value = CheckoutState.Error("Uang dibayar kurang dari total.")
            return
        }

        _checkout.value = CheckoutState.Submitting
        viewModelScope.launch {
            when (val result = sales.checkoutCash(cart.items(), paidAmount)) {
                is ResultState.Success -> {
                    cart.clear()
                    emitCart()
                    _checkout.value = CheckoutState.Success(result.data)
                    // Stock changed on the backend (SALE_OUT) — refresh labels.
                    refreshStock()
                }
                is ResultState.Error -> _checkout.value = CheckoutState.Error(result.message)
                ResultState.Loading -> Unit
            }
        }
    }

    /**
     * Save the cart as an OFFLINE CASH sale in the local queue. The cart is
     * cleared ONLY after the local save succeeds; on failure it is kept intact so
     * the cashier does not lose the transaction (Sprint 7 runtime rule). QRIS is
     * never eligible for this path — offline is CASH-only.
     */
    fun checkoutCashOffline(paidAmount: Double) {
        // UIX7-R015/R025 — reject a re-entrant submit (see checkoutCash).
        if (_checkout.value is CheckoutState.Submitting) return
        if (cart.isEmpty()) {
            _checkout.value = CheckoutState.Error("Keranjang kosong.")
            return
        }
        if (paidAmount < cart.subtotal()) {
            _checkout.value = CheckoutState.Error("Uang dibayar kurang dari total.")
            return
        }

        _checkout.value = CheckoutState.Submitting
        viewModelScope.launch {
            val total = cart.subtotal()
            when (val result = offline.createOfflineCashSale(cart.items(), paidAmount)) {
                is OfflineSaleRepository.SaveResult.Saved -> {
                    // Local save confirmed → safe to clear the cart now.
                    cart.clear()
                    emitCart()
                    _checkout.value = CheckoutState.OfflineSaved(
                        clientReference = result.clientReference,
                        grandTotal = total,
                        change = paidAmount - total,
                    )
                    refreshSyncCounts()
                }
                is OfflineSaleRepository.SaveResult.Error -> {
                    // Keep the cart so the cashier can retry.
                    _checkout.value = CheckoutState.Error(result.message)
                }
            }
        }
    }

    /** Manually trigger a sync of pending/failed offline sales. */
    fun syncNow() {
        if (_offlineSyncing.value == true) return
        _offlineSyncing.value = true
        viewModelScope.launch {
            offline.syncPending()
            refreshSyncCounts()
            _offlineSyncing.value = false
        }
    }

    /** Refresh the Pending/Failed offline-queue summary. */
    fun refreshSyncCounts() {
        viewModelScope.launch {
            _syncCounts.value = SyncCounts(
                pending = offline.pendingCount(),
                failed = offline.failedCount(),
            )
        }
    }

    fun resetCheckout() {
        _checkout.value = CheckoutState.Idle
    }

    private fun emitCart() {
        _cartItems.value = cart.items()
        _subtotal.value = cart.subtotal()
    }
}
