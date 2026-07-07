package com.aishtech.poslite.feature.cashier

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.recyclerview.widget.LinearLayoutManager
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.databinding.ActivityCashierBinding
import java.text.NumberFormat
import java.util.Locale

/**
 * Cashier foundation screen: manual sync, local product search, and a
 * cash-first in-memory cart. Checkout/payment is a Sprint 4 concern.
 */
class CashierActivity : AppCompatActivity() {

    private lateinit var binding: ActivityCashierBinding
    private lateinit var viewModel: CashierViewModel
    private lateinit var adapter: ProductListAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityCashierBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val context = applicationContext
        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    CashierViewModel(
                        catalogRepository = ServiceLocator.catalogRepository(context),
                        syncManager = ServiceLocator.catalogSyncManager(context),
                        sales = ServiceLocator.salesRepository(context),
                        cart = CartRepository(),
                    ) as T
            },
        )[CashierViewModel::class.java]

        setupList()
        observe()

        binding.buttonSync.setOnClickListener { viewModel.sync() }
        binding.buttonClearCart.setOnClickListener { viewModel.clearCart() }
        binding.buttonCheckout.setOnClickListener {
            val paid = binding.inputPaidAmount.text?.toString()?.toDoubleOrNull() ?: 0.0
            viewModel.checkoutCash(paid)
        }
        binding.inputSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) = Unit
            override fun onTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {
                viewModel.search(s?.toString().orEmpty())
            }
            override fun afterTextChanged(s: Editable?) = Unit
        })

        viewModel.search("")
    }

    private fun setupList() {
        adapter = ProductListAdapter(onAdd = { viewModel.addToCart(it) })
        binding.listProducts.layoutManager = LinearLayoutManager(this)
        binding.listProducts.adapter = adapter
    }

    private fun observe() {
        viewModel.products.observe(this) { products ->
            adapter.submitList(products)
            binding.textEmpty.visibility = if (products.isEmpty()) View.VISIBLE else View.GONE
        }
        viewModel.subtotal.observe(this) { subtotal ->
            binding.textCartTotal.text = "Total: ${formatPrice(subtotal)}"
        }
        viewModel.cartItems.observe(this) { items ->
            val count = items.sumOf { it.quantity }
            binding.textCartCount.text = "Keranjang: $count item"
            // Checkout is only possible with a non-empty cart.
            binding.buttonCheckout.isEnabled = items.isNotEmpty()
        }
        viewModel.syncStatus.observe(this) { binding.textSyncStatus.text = it }
        viewModel.syncing.observe(this) { syncing ->
            binding.buttonSync.isEnabled = !syncing
        }
        viewModel.checkout.observe(this) { state -> renderCheckout(state) }
    }

    private fun renderCheckout(state: CashierViewModel.CheckoutState) {
        val result = binding.textCheckoutResult
        when (state) {
            is CashierViewModel.CheckoutState.Idle -> {
                result.visibility = View.GONE
                binding.buttonCheckout.isEnabled = viewModel.cartItems.value?.isNotEmpty() ?: false
            }
            is CashierViewModel.CheckoutState.Submitting -> {
                result.visibility = View.VISIBLE
                result.text = getString(R.string.cashier_checkout_submitting)
                binding.buttonCheckout.isEnabled = false
            }
            is CashierViewModel.CheckoutState.Success -> {
                val sale = state.sale
                result.visibility = View.VISIBLE
                result.text = getString(R.string.cashier_checkout_success) +
                    "\nInvoice: ${sale.invoiceNumber}" +
                    "\nTotal: ${formatPrice(sale.grandTotal?.toDoubleOrNull() ?: 0.0)}" +
                    "\nKembalian: ${formatPrice(sale.changeTotal?.toDoubleOrNull() ?: 0.0)}"
                binding.inputPaidAmount.text?.clear()
            }
            is CashierViewModel.CheckoutState.Error -> {
                result.visibility = View.VISIBLE
                result.text = state.message
                binding.buttonCheckout.isEnabled = viewModel.cartItems.value?.isNotEmpty() ?: false
            }
        }
    }

    private fun formatPrice(value: Double): String {
        val format = NumberFormat.getNumberInstance(Locale("in", "ID"))
        format.maximumFractionDigits = 0
        return "Rp ${format.format(value)}"
    }
}
