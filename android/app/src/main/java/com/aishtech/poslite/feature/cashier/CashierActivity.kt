package com.aishtech.poslite.feature.cashier

import android.content.Intent
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
import com.aishtech.poslite.feature.receipt.ReceiptActivity
import com.aishtech.poslite.feature.reports.ReportsActivity
import com.aishtech.poslite.feature.sync.OfflineSalesSyncScheduler
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
                        offline = ServiceLocator.offlineSaleRepository(context),
                        stock = ServiceLocator.stockRepository(context),
                    ) as T
            },
        )[CashierViewModel::class.java]

        setupList()
        observe()

        binding.buttonSync.setOnClickListener { viewModel.sync() }
        binding.buttonReports.setOnClickListener {
            startActivity(Intent(this, ReportsActivity::class.java))
        }
        binding.buttonClearCart.setOnClickListener { viewModel.clearCart() }
        binding.buttonCheckout.setOnClickListener {
            val paid = binding.inputPaidAmount.text?.toString()?.toDoubleOrNull() ?: 0.0
            viewModel.checkoutCash(paid)
        }
        // Sprint 7 — offline CASH checkout: store locally, then kick a background
        // sync. The worker is network-constrained so it waits for connectivity.
        binding.buttonCheckoutOffline.setOnClickListener {
            val paid = binding.inputPaidAmount.text?.toString()?.toDoubleOrNull() ?: 0.0
            viewModel.checkoutCashOffline(paid)
            OfflineSalesSyncScheduler.enqueue(context)
        }
        binding.buttonSyncNow.setOnClickListener {
            viewModel.syncNow()
            OfflineSalesSyncScheduler.enqueue(context)
        }
        binding.inputSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) = Unit
            override fun onTextChanged(s: CharSequence?, a: Int, b: Int, c: Int) {
                viewModel.search(s?.toString().orEmpty())
            }
            override fun afterTextChanged(s: Editable?) = Unit
        })

        viewModel.search("")
        viewModel.refreshStock()
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
        // Sprint 8 — informational stock labels; the adapter re-renders when the
        // backend stock fetch resolves. Never blocks the product list or a sale.
        viewModel.stockLabels.observe(this) { labels ->
            adapter.setStockLabels(labels)
        }
        viewModel.subtotal.observe(this) { subtotal ->
            binding.textCartTotal.text = "Total: ${formatPrice(subtotal)}"
        }
        viewModel.cartItems.observe(this) { items ->
            val count = items.sumOf { it.quantity }
            binding.textCartCount.text = "Keranjang: $count item"
            // Checkout (online + offline) is only possible with a non-empty cart.
            binding.buttonCheckout.isEnabled = items.isNotEmpty()
            binding.buttonCheckoutOffline.isEnabled = items.isNotEmpty()
        }
        viewModel.syncStatus.observe(this) { binding.textSyncStatus.text = it }
        viewModel.syncing.observe(this) { syncing ->
            binding.buttonSync.isEnabled = !syncing
        }
        viewModel.syncCounts.observe(this) { counts ->
            binding.textSyncCounts.text =
                getString(R.string.cashier_sync_summary, counts.pending, counts.failed)
        }
        viewModel.offlineSyncing.observe(this) { syncing ->
            binding.buttonSyncNow.isEnabled = !syncing
            if (syncing) binding.textSyncStatus.text = getString(R.string.cashier_syncing)
        }
        viewModel.checkout.observe(this) { state -> renderCheckout(state) }

        viewModel.refreshSyncCounts()
    }

    private fun renderCheckout(state: CashierViewModel.CheckoutState) {
        val result = binding.textCheckoutResult
        when (state) {
            is CashierViewModel.CheckoutState.Idle -> {
                result.visibility = View.GONE
                result.setOnClickListener(null)
                val hasItems = viewModel.cartItems.value?.isNotEmpty() ?: false
                binding.buttonCheckout.isEnabled = hasItems
                binding.buttonCheckoutOffline.isEnabled = hasItems
            }
            is CashierViewModel.CheckoutState.Submitting -> {
                result.visibility = View.VISIBLE
                result.setOnClickListener(null)
                result.text = getString(R.string.cashier_checkout_submitting)
                binding.buttonCheckout.isEnabled = false
                binding.buttonCheckoutOffline.isEnabled = false
            }
            is CashierViewModel.CheckoutState.OfflineSaved -> {
                // Offline draft receipt — clearly NOT a final server receipt.
                result.visibility = View.VISIBLE
                result.setOnClickListener(null)
                result.text = getString(R.string.cashier_offline_saved) +
                    "\n" + getString(R.string.cashier_offline_draft_label) +
                    "\nRef: ${state.clientReference}" +
                    "\nTotal: ${formatPrice(state.grandTotal)}" +
                    "\nKembalian: ${formatPrice(state.change)}"
                binding.inputPaidAmount.text?.clear()
                val hasItems = viewModel.cartItems.value?.isNotEmpty() ?: false
                binding.buttonCheckout.isEnabled = hasItems
                binding.buttonCheckoutOffline.isEnabled = hasItems
            }
            is CashierViewModel.CheckoutState.Success -> {
                val sale = state.sale
                result.visibility = View.VISIBLE
                result.text = getString(R.string.cashier_checkout_success) +
                    "\nInvoice: ${sale.invoiceNumber}" +
                    "\nTotal: ${formatPrice(sale.grandTotal?.toDoubleOrNull() ?: 0.0)}" +
                    "\nKembalian: ${formatPrice(sale.changeTotal?.toDoubleOrNull() ?: 0.0)}" +
                    "\n" + getString(R.string.cashier_view_receipt)
                // Sprint 6 — tap the result to open the receipt for this sale.
                result.setOnClickListener { openReceipt(sale.id) }
                binding.inputPaidAmount.text?.clear()
                val hasItems = viewModel.cartItems.value?.isNotEmpty() ?: false
                binding.buttonCheckout.isEnabled = hasItems
                binding.buttonCheckoutOffline.isEnabled = hasItems
            }
            is CashierViewModel.CheckoutState.Error -> {
                result.visibility = View.VISIBLE
                result.setOnClickListener(null)
                result.text = state.message
                val hasItems = viewModel.cartItems.value?.isNotEmpty() ?: false
                binding.buttonCheckout.isEnabled = hasItems
                binding.buttonCheckoutOffline.isEnabled = hasItems
            }
        }
    }

    private fun openReceipt(saleId: Long) {
        val intent = Intent(this, ReceiptActivity::class.java)
            .putExtra(ReceiptActivity.EXTRA_SALE_ID, saleId)
        startActivity(intent)
    }

    private fun formatPrice(value: Double): String {
        val format = NumberFormat.getNumberInstance(Locale("in", "ID"))
        format.maximumFractionDigits = 0
        return "Rp ${format.format(value)}"
    }
}
