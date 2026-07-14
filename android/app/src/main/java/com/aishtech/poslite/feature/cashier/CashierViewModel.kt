package com.aishtech.poslite.feature.cashier

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.data.remote.dto.SaleDto
import com.aishtech.poslite.data.repository.AuthRepository
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.data.repository.CatalogRepository
import com.aishtech.poslite.data.repository.OfflineSaleRepository
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.data.repository.StockRepository
import com.aishtech.poslite.feature.sync.CatalogSyncManager
import kotlinx.coroutines.launch
import java.util.UUID

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
    private val auth: AuthRepository? = null,
    private val deviceName: String = "",
    private val allCategoriesLabel: String = "Semua",
    private val referenceProvider: () -> String = { UUID.randomUUID().toString() },
) : ViewModel() {

    // UIX8C-R066/R074/R075 — the current filter is the (query, categoryId) pair.
    // Search and category selection each mutate exactly one axis and re-run the
    // SAME combined query, so they compose and neither ever touches the cart.
    private var currentQuery: String = ""
    private var selectedCategoryId: Long? = null

    // UIX7-R054 — the stable idempotency key for the CURRENT online checkout
    // attempt. Minted once per cart, REUSED across retries (so a retry after a
    // timeout is deduped by the backend rather than creating a second sale), and
    // reset on success or any cart mutation (a changed cart is a new transaction).
    private var pendingOnlineReference: String? = null

    /** UI state for the checkout flow. */
    sealed class CheckoutState {
        data object Idle : CheckoutState()
        data object Submitting : CheckoutState()
        data class Success(val sale: SaleDto) : CheckoutState()
        // Sprint 7 — the sale is stored locally as an OFFLINE DRAFT (not synced).
        // UIX-8 — draft totals are whole-rupiah Long (integer-exact, never float).
        data class OfflineSaved(val clientReference: String, val grandTotal: Long, val change: Long) : CheckoutState()
        data class Error(val message: String) : CheckoutState()
    }

    /**
     * UIX8B-R023/R024/R029 — truthful product-list state. A load distinguishes
     * "still loading", "catalog is empty (needs sync)", "no search match", and
     * "load failed" instead of silently swapping to an empty list. A failure NEVER
     * clears the cart (the cart is separate authoritative state).
     */
    sealed class ProductsState {
        data object Loading : ProductsState()
        data class Loaded(val products: List<LocalProductEntity>) : ProductsState()
        data object EmptyCatalog : ProductsState()
        data class NoMatch(val query: String) : ProductsState()
        data class Error(val message: String) : ProductsState()
    }

    /** Summary of the local offline sync queue shown to the cashier. */
    data class SyncCounts(val pending: Int, val failed: Int)

    private val _products = MutableLiveData<List<LocalProductEntity>>(emptyList())
    val products: LiveData<List<LocalProductEntity>> = _products

    private val _productsState = MutableLiveData<ProductsState>(ProductsState.Loading)
    val productsState: LiveData<ProductsState> = _productsState

    private val _cartItems = MutableLiveData<List<CartItem>>(emptyList())
    val cartItems: LiveData<List<CartItem>> = _cartItems

    private val _subtotal = MutableLiveData(0.0)
    val subtotal: LiveData<Double> = _subtotal

    // UIX8B-R033/R044 — the authoritative cart total is whole-rupiah integer.
    // The UI renders THIS (never a recomputed float) so the displayed total can
    // never diverge from the checkout amount.
    private val _subtotalRupiah = MutableLiveData(0L)
    val subtotalRupiah: LiveData<Long> = _subtotalRupiah

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

    // UIX8C-R061/R062 — canonical cashier context header. Starts offline/unknown
    // ("Tidak tersedia") and is filled from auth/me; a failure keeps a truthful
    // offline presentation rather than a stale claim.
    private val _context = MutableLiveData(
        CashierContextPresenter.present(me = null, deviceName = deviceName, reachable = false),
    )
    val context: LiveData<CashierContext> = _context

    // UIX8C-R074/R075 — the category filter chip row ("Semua" + active categories).
    private val _categories = MutableLiveData<List<CategoryOption>>(
        listOf(CategoryOption(id = null, name = allCategoriesLabel, selected = true)),
    )
    val categories: LiveData<List<CategoryOption>> = _categories

    /** Resolve the canonical cashier context for the home header. Best-effort:
     *  a failure leaves the truthful offline presentation intact. */
    fun loadContext() {
        val repo = auth ?: return
        viewModelScope.launch {
            when (val result = repo.me()) {
                is ResultState.Success ->
                    _context.value = CashierContextPresenter.present(result.data, deviceName, reachable = true)
                is ResultState.Error ->
                    _context.value = CashierContextPresenter.present(null, deviceName, reachable = false)
                ResultState.Loading -> Unit
            }
        }
    }

    /** Load the active categories for the filter row, preserving the selection. */
    fun loadCategories() {
        viewModelScope.launch {
            try {
                val loaded = catalogRepository.categories()
                _categories.value = CategoryOption.build(loaded, selectedCategoryId, allCategoriesLabel)
            } catch (_: Exception) {
                // Categories are a navigation aid only; on failure keep "Semua".
                _categories.value = listOf(CategoryOption(id = null, name = allCategoriesLabel, selected = selectedCategoryId == null))
            }
        }
    }

    /**
     * Update the search term. Search only re-queries the product list against the
     * current category; it NEVER mutates the cart (UIX8C-R074).
     */
    fun search(query: String) {
        currentQuery = query
        applyFilters()
    }

    /**
     * Select a category (null = "Semua"/all). Re-queries the product list under
     * the current search term; clearing to null restores the canonical catalog
     * (UIX8C-R075). Never mutates the cart (UIX8C-R074).
     */
    fun selectCategory(categoryId: Long?) {
        if (categoryId == selectedCategoryId) return
        selectedCategoryId = categoryId
        _categories.value = _categories.value?.map { it.copy(selected = it.id == categoryId) }
        applyFilters()
    }

    /** UIX8C-R069 — re-run the current filter after an error, without changing
     *  the query, category, or cart. */
    fun retry() = applyFilters()

    /** Re-run the combined (query, category) query and map the result to a
     *  truthful product state. A failure surfaces Error but NEVER clears the cart. */
    private fun applyFilters() {
        _productsState.value = ProductsState.Loading
        val query = currentQuery
        val categoryId = selectedCategoryId
        viewModelScope.launch {
            try {
                val results = catalogRepository.search(query, categoryId)
                _products.value = results
                _productsState.value =
                    if (results.isNotEmpty()) ProductsState.Loaded(results)
                    else emptyProductsState(query, filterActive = categoryId != null)
            } catch (e: Exception) {
                // UIX8B-R029 / UIX8C-R069 — a product-load failure surfaces an error
                // state but NEVER clears the cart; the last-known list is untouched.
                _productsState.value = ProductsState.Error(e.message.orEmpty())
            }
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
                    loadCategories()
                    applyFilters()
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
        pendingOnlineReference = null
        emitCart()
    }

    fun updateQuantity(productId: Long, quantity: Int) {
        cart.updateQuantity(productId, quantity)
        pendingOnlineReference = null
        emitCart()
    }

    fun removeItem(productId: Long) {
        cart.removeProduct(productId)
        pendingOnlineReference = null
        emitCart()
    }

    fun clearCart() {
        cart.clear()
        pendingOnlineReference = null
        emitCart()
    }

    /**
     * Submit the cart as an online CASH sale. The cart is cleared ONLY after the
     * backend confirms the sale; on any failure the cart is kept intact so the
     * cashier can retry (Sprint 4 runtime rule).
     */
    fun checkoutCash(paidAmount: Long) {
        // UIX7-R015/R025 — a submission is already in flight; ignore the repeat
        // tap so a double-press can never create two server transactions. The UI
        // also disables the button, but this guard closes the tap-before-observer
        // race at the source.
        if (_checkout.value is CheckoutState.Submitting) return
        if (cart.isEmpty()) {
            _checkout.value = CheckoutState.Error("Keranjang kosong.")
            return
        }
        // UIX-8 — integer-exact sufficiency check (whole rupiah), never float.
        if (!RupiahMoney.isSufficient(paidAmount, cart.subtotalRupiah())) {
            _checkout.value = CheckoutState.Error("Uang dibayar kurang dari total.")
            return
        }

        _checkout.value = CheckoutState.Submitting
        // Mint the idempotency key once; a retry after a failure re-uses the same
        // key so the backend dedupes instead of duplicating the sale (UIX7-R054/R055).
        val reference = pendingOnlineReference ?: referenceProvider().also { pendingOnlineReference = it }
        viewModelScope.launch {
            when (val result = sales.checkoutCash(cart.items(), paidAmount, reference)) {
                is ResultState.Success -> {
                    cart.clear()
                    emitCart()
                    // Sale confirmed by the server → this key is spent; the next
                    // cart gets a fresh one.
                    pendingOnlineReference = null
                    _checkout.value = CheckoutState.Success(result.data)
                    // Stock changed on the backend (SALE_OUT) — refresh labels.
                    refreshStock()
                }
                // Keep pendingOnlineReference on failure so a retry re-uses it.
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
    fun checkoutCashOffline(paidAmount: Long) {
        // UIX7-R015/R025 — reject a re-entrant submit (see checkoutCash).
        if (_checkout.value is CheckoutState.Submitting) return
        if (cart.isEmpty()) {
            _checkout.value = CheckoutState.Error("Keranjang kosong.")
            return
        }
        // UIX-8 — integer-exact sufficiency check (whole rupiah), never float.
        if (!RupiahMoney.isSufficient(paidAmount, cart.subtotalRupiah())) {
            _checkout.value = CheckoutState.Error("Uang dibayar kurang dari total.")
            return
        }

        _checkout.value = CheckoutState.Submitting
        viewModelScope.launch {
            val total = cart.subtotalRupiah()
            when (val result = offline.createOfflineCashSale(cart.items(), paidAmount)) {
                is OfflineSaleRepository.SaveResult.Saved -> {
                    // Local save confirmed → safe to clear the cart now.
                    cart.clear()
                    emitCart()
                    _checkout.value = CheckoutState.OfflineSaved(
                        clientReference = result.clientReference,
                        grandTotal = total,
                        change = RupiahMoney.change(paidAmount, total),
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
        _subtotalRupiah.value = cart.subtotalRupiah()
    }

    companion object {
        /**
         * UIX8B-R023 — decide the truthful empty state when a search returns no
         * products. A blank query with no results means the local catalog itself
         * is empty (needs a sync); a non-blank query means no product matched the
         * term. Pure and side-effect-free so it is unit-testable without the Room/
         * repository stack.
         */
        fun emptyProductsState(query: String): ProductsState = emptyProductsState(query, filterActive = false)

        /**
         * UIX8C-R067/R068 — filter-aware truthful empty state. Only a blank query
         * with NO active category filter is a genuinely empty catalog (needs a
         * sync); a search term OR an active category with no results is a
         * "no match" (never presented as an empty catalog).
         */
        fun emptyProductsState(query: String, filterActive: Boolean): ProductsState =
            if (query.isBlank() && !filterActive) ProductsState.EmptyCatalog
            else ProductsState.NoMatch(query)
    }
}
