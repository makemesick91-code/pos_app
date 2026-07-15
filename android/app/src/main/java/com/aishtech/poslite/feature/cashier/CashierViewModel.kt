package com.aishtech.poslite.feature.cashier

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.map
import androidx.lifecycle.viewModelScope
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.core.util.Event
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

    // UIX7-R054 / UIX8C-R097 — the ONE stable idempotency key for the current
    // logical checkout. Minted once per cart and REUSED across: the online submit,
    // a retry after a lost response, AND a governed online→offline fallback (so the
    // offline row, the eventual sync, and the backend all dedupe on the same key
    // rather than creating a second sale). It is reset only on a durable success or
    // any cart mutation (a changed cart is a new logical transaction).
    private var pendingCheckoutReference: String? = null

    /** Mint the stable checkout reference once, then reuse it (UIX8C-R097). */
    private fun checkoutReference(): String =
        pendingCheckoutReference ?: referenceProvider().also { pendingCheckoutReference = it }

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

    // UIX8C-R146 — the truthful PRESENTATION projection of the canonical checkout
    // state. This is derived (never a second source of truth): the mapper is pure
    // and the observer (Activity) renders THIS so "queued locally" can never be
    // confused with "synced on the server". Sync-queue rows project through
    // [PaymentUiStateMapper.fromSyncStatus] on the history/recovery surface.
    val paymentUiState: LiveData<PaymentUiState> =
        _checkout.map { PaymentUiStateMapper.fromCheckout(it) }

    // UIX8C-R156 — a one-shot, informative reconnect signal. It NEVER creates sync
    // work itself (that stays with the canonical unique-work scheduler); it only
    // tells the UI that connectivity returned and any pending queue can resume.
    private val _reconnected = MutableLiveData<Event<Unit>>()
    val reconnected: LiveData<Event<Unit>> = _reconnected

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
        pendingCheckoutReference = null
        emitCart()
    }

    fun updateQuantity(productId: Long, quantity: Int) {
        cart.updateQuantity(productId, quantity)
        pendingCheckoutReference = null
        emitCart()
    }

    fun removeItem(productId: Long) {
        cart.removeProduct(productId)
        pendingCheckoutReference = null
        emitCart()
    }

    fun clearCart() {
        cart.clear()
        pendingCheckoutReference = null
        emitCart()
    }

    /**
     * Submit the cart as an online CASH sale, with a GOVERNED offline fallback
     * (UIX-8C-04). The cart is cleared ONLY after either the backend confirms the
     * sale OR a durable offline row is committed:
     *
     *  - server ACK            → cart cleared, [CheckoutState.Success].
     *  - governed transport
     *    failure (DNS/timeout/
     *    connect/reset)        → durable offline CASH save reusing the SAME stable
     *                            clientReference; on a durable save the cart is
     *                            cleared and [CheckoutState.OfflineSaved] is shown
     *                            (UIX8C-R098/R105/R107). A local-save failure keeps
     *                            the cart (UIX8C-R108).
     *  - canonical rejection
     *    (any HTTP status)     → cart KEPT, [CheckoutState.Error]; NEVER offline
     *                            success (UIX8C-R099..R102).
     *  - TLS/unknown error     → cart KEPT, [CheckoutState.Error]; NEVER offline
     *                            (UIX8C-R103).
     *
     * QRIS is never eligible here — offline is CASH-only (UIX8C-R096).
     */
    fun checkoutCash(paidAmount: Long) {
        // UIX7-R015/R025 / UIX8C-R109 — a submission is already in flight; ignore
        // the repeat tap so a double-press can never create two transactions. The
        // UI also disables the button, but this guard closes the tap-before-observer
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
        // Mint the ONE stable idempotency key once; reused across a retry AND the
        // offline fallback below so the whole chain dedupes (UIX7-R054 / UIX8C-R097).
        val reference = checkoutReference()
        val items = cart.items()
        val total = cart.subtotalRupiah()
        viewModelScope.launch {
            when (val outcome = sales.submitCash(items, paidAmount, reference)) {
                is SalesRepository.CheckoutOutcome.Success -> {
                    cart.clear()
                    emitCart()
                    // Sale confirmed by the server → this key is spent; the next
                    // cart gets a fresh one.
                    pendingCheckoutReference = null
                    _checkout.value = CheckoutState.Success(outcome.sale)
                    // Stock changed on the backend (SALE_OUT) — refresh labels.
                    refreshStock()
                }
                // UIX8C-R098 — governed transport failure → durable offline CASH
                // fallback, reusing the SAME reference so sync/backend dedupe.
                is SalesRepository.CheckoutOutcome.TransportUnavailable ->
                    saveOfflineFallback(items, paidAmount, reference, total)
                // UIX8C-R099..R103 — a canonical rejection or unsafe error must
                // NEVER become offline success; keep the cart, show the reason.
                is SalesRepository.CheckoutOutcome.Rejected ->
                    _checkout.value = CheckoutState.Error(outcome.message)
                is SalesRepository.CheckoutOutcome.Failed ->
                    _checkout.value = CheckoutState.Error(outcome.message)
            }
        }
    }

    /**
     * UIX8C-R105/R106/R107/R108 — commit the durable offline CASH row (reusing the
     * stable [reference]) and clear the cart ONLY on a durable save; a save failure
     * preserves the cart. The [CheckoutState.OfflineSaved] state drives the truthful
     * "saved on device, waiting for sync" UI and the sync enqueue.
     */
    private suspend fun saveOfflineFallback(
        items: List<CartItem>,
        paidAmount: Long,
        reference: String,
        total: Long,
    ) {
        when (val result = offline.createOfflineCashSale(items, paidAmount, clientReference = reference)) {
            is OfflineSaleRepository.SaveResult.Saved -> {
                cart.clear()
                emitCart()
                _checkout.value = CheckoutState.OfflineSaved(
                    clientReference = result.clientReference,
                    grandTotal = total,
                    change = RupiahMoney.change(paidAmount, total),
                )
                refreshSyncCounts()
            }
            is OfflineSaleRepository.SaveResult.Error ->
                _checkout.value = CheckoutState.Error(result.message)
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
        // UIX8C-R097 — reuse the ONE stable reference (an earlier online attempt on
        // this cart may already hold one) so the manual-offline path and any prior
        // online attempt dedupe on the same key.
        val reference = checkoutReference()
        val items = cart.items()
        val total = cart.subtotalRupiah()
        viewModelScope.launch {
            saveOfflineFallback(items, paidAmount, reference, total)
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

    /**
     * UIX8C-R157/R158/R159 — a SAFE, governed manual retry of the offline sync
     * queue. It does NOT create a new checkout, a new `clientReference`, a new
     * offline row, or a second sync pipeline: it delegates to the canonical
     * [OfflineSaleRepository.syncPending], which reuses each existing row (deduped
     * on its stable `clientReference`), respects the bounded [MAX_SYNC_ATTEMPTS]
     * cap, and reconciles idempotently. The [_offlineSyncing] guard prevents a
     * second manual retry from racing an in-flight one for the same rows.
     */
    fun requestManualRetry() = syncNow()

    /**
     * UIX8C-R156 — record that connectivity was restored. This only refreshes the
     * truthful queue counts and emits a one-shot informative signal; it deliberately
     * does NOT enqueue sync work (the caller uses the canonical unique-work
     * scheduler, which dedupes) and never marks anything SYNCED.
     */
    fun onConnectivityRestored() {
        refreshSyncCounts()
        _reconnected.value = Event(Unit)
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
