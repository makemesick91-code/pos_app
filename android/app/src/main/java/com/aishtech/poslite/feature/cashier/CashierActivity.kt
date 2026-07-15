package com.aishtech.poslite.feature.cashier

import android.content.Intent
import android.net.ConnectivityManager
import android.net.Network
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.lifecycleScope
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.LinearLayoutManager
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.device.DeviceInfoProvider
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.core.util.EventObserver
import com.aishtech.poslite.data.repository.CartRepository
import com.aishtech.poslite.databinding.ActivityCashierBinding
import com.aishtech.poslite.feature.history.TransactionHistoryActivity
import com.aishtech.poslite.feature.receipt.ReceiptActivity
import com.aishtech.poslite.feature.reports.ReportsActivity
import com.aishtech.poslite.feature.sync.OfflineSalesSyncScheduler
import kotlinx.coroutines.launch

/**
 * Cashier foundation screen: manual sync, local product search, and a
 * cash-first in-memory cart. Checkout/payment is a Sprint 4 concern.
 */
class CashierActivity : AppCompatActivity(), PaymentSheetFragment.Host {

    private lateinit var binding: ActivityCashierBinding
    private lateinit var viewModel: CashierViewModel
    private lateinit var adapter: ProductListAdapter
    private lateinit var categoryAdapter: CategoryFilterAdapter

    // UIX8C-R156 — reconnect detection (registered in onStart, cleared in onStop).
    private var networkCallback: ConnectivityManager.NetworkCallback? = null
    private var networkWasLost = false

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
                        auth = ServiceLocator.authRepository(context),
                        deviceName = DeviceInfoProvider.deviceName(),
                        allCategoriesLabel = getString(R.string.cashier_category_all),
                    ) as T
            },
        )[CashierViewModel::class.java]

        setupList()
        observe()

        // Sprint 10 — best-effort device heartbeat once an authenticated cashier
        // session is active. Failures are ignored here (the status screen surfaces
        // any blocked state); this never blocks the cashier UI.
        lifecycleScope.launch {
            ServiceLocator.deviceRepository(context).heartbeat()
        }

        binding.buttonSync.setOnClickListener { viewModel.sync() }
        binding.buttonReports.setOnClickListener {
            startActivity(Intent(this, ReportsActivity::class.java))
        }
        binding.buttonHistory.setOnClickListener {
            startActivity(Intent(this, TransactionHistoryActivity::class.java))
        }
        // UIX7-R016 — clearing the cart is destructive; confirm before discarding
        // so an accidental tap can never wipe an in-progress sale. Uses the
        // canonical UIX-1 microcopy already shipped in strings.xml.
        binding.buttonClearCart.setOnClickListener { confirmClearCart() }
        // UIX-8B — the single checkout CTA opens the native cash tender sheet
        // (quick tender, manual entry, integer-exact change). Online and offline
        // confirm both live in the sheet and delegate to the same guarded VM, so
        // the double-submit guard, stable clientReference, and durable-save
        // protections are unchanged. The legacy inline paid field + separate
        // offline button are superseded and taken out of the flow.
        binding.buttonCheckout.setOnClickListener { openPaymentSheet() }
        binding.inputPaidAmount.visibility = View.GONE
        binding.buttonCheckoutOffline.visibility = View.GONE
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
        // UIX8C-R075 — clearing the search restores the catalog under the current
        // category. Clears the field (which re-runs search("") via the watcher).
        binding.buttonClearSearch.setOnClickListener { binding.inputSearch.text?.clear() }
        // UIX8C-R069 — retry re-runs the current filter without touching the cart.
        binding.buttonRetryProducts.setOnClickListener { viewModel.retry() }

        viewModel.search("")
        viewModel.refreshStock()
        viewModel.loadCategories()
        viewModel.loadContext()
    }

    // UIX8C-R156 — observe default-network availability for a truthful reconnect
    // signal. Only a genuine unavailable→available transition fires the one-shot
    // feedback (the callback fires onAvailable at registration too, which we skip);
    // it NEVER creates sync work itself — the governed WorkManager scheduler owns
    // the actual reconnect sync via its NetworkType.CONNECTED constraint.
    override fun onStart() {
        super.onStart()
        val cm = getSystemService(ConnectivityManager::class.java) ?: return
        val callback = object : ConnectivityManager.NetworkCallback() {
            override fun onLost(network: Network) {
                networkWasLost = true
            }

            override fun onAvailable(network: Network) {
                if (networkWasLost) {
                    networkWasLost = false
                    runOnUiThread { viewModel.onConnectivityRestored() }
                }
            }
        }
        networkCallback = callback
        cm.registerDefaultNetworkCallback(callback)
    }

    override fun onStop() {
        super.onStop()
        networkCallback?.let { cb ->
            getSystemService(ConnectivityManager::class.java)?.unregisterNetworkCallback(cb)
        }
        networkCallback = null
    }

    private fun setupList() {
        adapter = ProductListAdapter(onAdd = { viewModel.addToCart(it) })
        binding.listProducts.layoutManager = LinearLayoutManager(this)
        binding.listProducts.adapter = adapter

        // UIX8C-R074 — horizontal category filter. Selecting re-queries products
        // only; it never mutates the cart.
        categoryAdapter = CategoryFilterAdapter(onSelect = { viewModel.selectCategory(it.id) })
        binding.listCategories.layoutManager =
            LinearLayoutManager(this, LinearLayoutManager.HORIZONTAL, false)
        binding.listCategories.adapter = categoryAdapter
    }

    private fun observe() {
        viewModel.products.observe(this) { products ->
            adapter.submitList(products)
        }
        // UIX8B-R023/R024 — truthful product-list state (loading / empty-catalog /
        // no-match / error) instead of a silent empty swap.
        viewModel.productsState.observe(this) { renderProducts(it) }
        // Sprint 8 — informational stock labels; the adapter re-renders when the
        // backend stock fetch resolves. Never blocks the product list or a sale.
        viewModel.stockLabels.observe(this) { labels ->
            adapter.setStockLabels(labels)
        }
        // UIX8B-R044 — render the authoritative whole-rupiah integer total, never
        // a recomputed float, so the shown total matches the checkout amount.
        viewModel.subtotalRupiah.observe(this) { total ->
            binding.textCartTotal.text = "Total: ${RupiahMoney.format(total)}"
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
        // UIX8C-R156 — one-shot, informative reconnect feedback. It does NOT
        // create sync work (the canonical unique-work scheduler dedupes that);
        // it only tells the operator the pending queue can resume.
        viewModel.reconnected.observe(this, EventObserver {
            Toast.makeText(this, R.string.cashier_reconnected, Toast.LENGTH_SHORT).show()
        })
        // UIX8C-R061/R062 — canonical cashier context header.
        viewModel.context.observe(this) { renderContext(it) }
        // UIX8C-R074 — category filter chips.
        viewModel.categories.observe(this) { categoryAdapter.submitList(it) }

        viewModel.refreshSyncCounts()
    }

    // UIX8C-R062 — bind the canonical business/outlet/cashier/device context and a
    // truthful online/offline chip (text + colour token, never colour alone).
    private fun renderContext(context: CashierContext) {
        val header = binding.cashierContext
        header.textContextBusiness.text = context.businessName
        header.textContextOutlet.text = context.outletName
        header.textContextCashier.text = context.cashierLine
        header.textContextDevice.text = context.deviceName
        val chip = header.chipNetwork
        if (context.online) {
            chip.text = getString(R.string.ctx_network_online)
            chip.backgroundTintList = ContextCompat.getColorStateList(this, R.color.state_online_bg)
            chip.setTextColor(ContextCompat.getColor(this, R.color.state_online_fg))
        } else {
            chip.text = getString(R.string.ctx_network_offline)
            chip.backgroundTintList = ContextCompat.getColorStateList(this, R.color.state_offline_bg)
            chip.setTextColor(ContextCompat.getColor(this, R.color.state_offline_fg))
        }
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
                // UIX8C-R114 — a durable offline row exists (whether the operator
                // chose offline OR an online attempt degraded via the governed
                // fallback). Schedule the bounded, connectivity-constrained sync
                // here so BOTH paths reconnect-and-sync (enqueueUniqueWork KEEP is
                // idempotent, so a redundant enqueue is harmless).
                OfflineSalesSyncScheduler.enqueue(applicationContext)
                // Offline draft receipt — clearly NOT a final server receipt.
                // UIX-8C-06 — tapping opens the truthful offline/PENDING receipt
                // bound to this durable transaction's stable clientReference.
                result.visibility = View.VISIBLE
                val offlineRef = state.clientReference
                result.setOnClickListener {
                    startActivity(ReceiptActivity.forOfflineReference(this, offlineRef))
                }
                result.text = getString(R.string.cashier_offline_saved) +
                    "\n" + getString(R.string.cashier_offline_draft_label) +
                    "\n" + getString(R.string.cashier_offline_waiting_sync) +
                    "\nRef: ${state.clientReference}" +
                    "\nTotal: ${RupiahMoney.format(state.grandTotal)}" +
                    "\nKembalian: ${RupiahMoney.format(state.change)}" +
                    "\n" + getString(R.string.cashier_view_receipt)
                binding.inputPaidAmount.text?.clear()
                val hasItems = viewModel.cartItems.value?.isNotEmpty() ?: false
                binding.buttonCheckout.isEnabled = hasItems
                binding.buttonCheckoutOffline.isEnabled = hasItems
            }
            is CashierViewModel.CheckoutState.Success -> {
                val sale = state.sale
                result.visibility = View.VISIBLE
                // UIX8B-R044/R047/R063 — the canonical server total/change are
                // whole-rupiah strings; parse to Long and render through the single
                // formatter. A missing value renders "Tidak tersedia" (never a
                // fabricated 0 via the old toDoubleOrNull() ?: 0.0 float path).
                result.text = getString(R.string.cashier_checkout_success) +
                    "\nInvoice: ${sale.invoiceNumber}" +
                    "\nTotal: ${RupiahMoney.formatOrUnavailable(RupiahMoney.parse(sale.grandTotal))}" +
                    "\nKembalian: ${RupiahMoney.formatOrUnavailable(RupiahMoney.parse(sale.changeTotal))}" +
                    "\n" + getString(R.string.cashier_view_receipt)
                // Sprint 6 — tap the result to open the receipt for this sale.
                // UIX-8C-06 — carry the stable clientReference so the receipt is
                // bound to this exact logical transaction.
                result.setOnClickListener { openReceipt(sale.id, sale.clientReference) }
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

    private fun confirmClearCart() {
        val count = viewModel.cartItems.value?.sumOf { it.quantity } ?: 0
        if (count == 0) {
            viewModel.clearCart()
            return
        }
        AlertDialog.Builder(this)
            .setMessage(getString(R.string.uix_clear_cart_confirm, count))
            .setNegativeButton(R.string.uix_action_back, null)
            .setPositiveButton(R.string.uix_action_clear) { _, _ -> viewModel.clearCart() }
            .show()
    }

    private fun openReceipt(saleId: Long, clientReference: String?) {
        startActivity(ReceiptActivity.forServerSale(this, saleId, clientReference))
    }

    // UIX-8B — open the native cash tender sheet for the current cart total. The
    // sheet is presentation-only; confirming routes back through onCashTender to
    // the guarded ViewModel checkout. Never opened for an empty/zero cart.
    private fun openPaymentSheet() {
        val due = viewModel.subtotalRupiah.value ?: 0L
        if (due <= 0L || viewModel.cartItems.value.isNullOrEmpty()) return
        PaymentSheetFragment.newInstance(due)
            .show(supportFragmentManager, PaymentSheetFragment.TAG)
    }

    override fun onCashTender(paidAmount: Long, offline: Boolean) {
        // Both paths schedule the offline sync when they reach OfflineSaved
        // (see renderCheckout); the online path additionally falls back to a
        // durable offline save on a governed transport failure (UIX-8C-04).
        if (offline) {
            viewModel.checkoutCashOffline(paidAmount)
        } else {
            viewModel.checkoutCash(paidAmount)
        }
    }

    // UIX8B-R023/R024/R029 — drive the product area's truthful states. A load
    // failure surfaces an error message but never clears the last product list or
    // the cart; the RecyclerView keeps its content behind the overlay.
    private fun renderProducts(state: CashierViewModel.ProductsState) {
        val progress = binding.progressProducts
        val empty = binding.textEmpty
        val retry = binding.buttonRetryProducts
        when (state) {
            CashierViewModel.ProductsState.Loading -> {
                progress.visibility = View.VISIBLE
                empty.visibility = View.GONE
                retry.visibility = View.GONE
            }
            is CashierViewModel.ProductsState.Loaded -> {
                progress.visibility = View.GONE
                empty.visibility = View.GONE
                retry.visibility = View.GONE
            }
            CashierViewModel.ProductsState.EmptyCatalog -> {
                progress.visibility = View.GONE
                empty.text = getString(R.string.cashier_empty)
                empty.visibility = View.VISIBLE
                retry.visibility = View.GONE
            }
            is CashierViewModel.ProductsState.NoMatch -> {
                progress.visibility = View.GONE
                empty.text = getString(R.string.cashier_products_no_match)
                empty.visibility = View.VISIBLE
                retry.visibility = View.GONE
            }
            is CashierViewModel.ProductsState.Error -> {
                progress.visibility = View.GONE
                empty.text = getString(R.string.cashier_products_error)
                empty.visibility = View.VISIBLE
                // UIX8C-R069 — offer an explicit retry in the error state.
                retry.visibility = View.VISIBLE
            }
        }
    }
}
