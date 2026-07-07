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
import com.aishtech.poslite.data.repository.SalesRepository
import com.aishtech.poslite.feature.sync.CatalogSyncManager
import kotlinx.coroutines.launch

/**
 * Drives the cashier screen: local product search, cash-first cart, manual
 * catalog sync, and (Sprint 4) online CASH checkout to the backend.
 */
class CashierViewModel(
    private val catalogRepository: CatalogRepository,
    private val syncManager: CatalogSyncManager,
    private val sales: SalesRepository,
    private val cart: CartRepository,
) : ViewModel() {

    /** UI state for the checkout flow. */
    sealed class CheckoutState {
        data object Idle : CheckoutState()
        data object Submitting : CheckoutState()
        data class Success(val sale: SaleDto) : CheckoutState()
        data class Error(val message: String) : CheckoutState()
    }

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

    fun search(query: String) {
        viewModelScope.launch {
            _products.value = catalogRepository.search(query)
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
                }
                is ResultState.Error -> _checkout.value = CheckoutState.Error(result.message)
                ResultState.Loading -> Unit
            }
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
