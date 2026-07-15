package com.aishtech.poslite.feature.receipt

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.core.util.EventObserver
import com.aishtech.poslite.databinding.ActivityReceiptBinding
import com.aishtech.poslite.databinding.ItemReceiptLineBinding
import com.aishtech.poslite.feature.printer.PrintOutcome

/**
 * UIX-8C-06 — premium receipt / transaction-detail screen. It binds to exactly
 * one canonical transaction and renders its truthful state, context, items,
 * whole-rupiah totals, tender and change. It is launched three governed ways:
 *  - [forServerSale] — an online-acknowledged sale (server sale id).
 *  - [forOfflineReference] — a just-saved durable offline transaction (client ref).
 *  - [forLocalTransaction] — a durable local transaction reopened from history.
 *
 * A previous transaction's result can never surface here: each launch reloads for
 * its own identity, print feedback is a one-shot [EventObserver], and the receipt
 * never reconstructs values from mutable cart state. Printing is a presentation
 * action routed through the coordinator; it never alters transaction authority.
 */
class ReceiptActivity : AppCompatActivity() {

    private lateinit var binding: ActivityReceiptBinding
    private lateinit var viewModel: ReceiptViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityReceiptBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val app = applicationContext
        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ReceiptViewModel(
                        receipts = ServiceLocator.receiptRepository(app),
                        offline = ServiceLocator.offlineSaleRepository(app),
                        coordinator = ServiceLocator.printerCoordinator(app),
                    ) as T
            },
        )[ReceiptViewModel::class.java]

        binding.buttonPrint.isEnabled = false
        binding.buttonPrint.setOnClickListener { viewModel.print() }
        binding.buttonNewTransaction.setOnClickListener { finish() }

        viewModel.state.observe(this) { render(it) }
        viewModel.printing.observe(this) { printing ->
            binding.progress.visibility = if (printing) View.VISIBLE else View.GONE
        }
        viewModel.printEvent.observe(this, EventObserver { outcome -> announcePrint(outcome) })

        // ViewModel is per-Activity; a config change re-observes the same bound
        // projection, so we only trigger a load on first creation to avoid
        // re-fetching (and never replaying a stale one-shot).
        if (savedInstanceState == null) {
            dispatchLoad()
        }
    }

    private fun dispatchLoad() {
        val localId = intent.getLongExtra(EXTRA_LOCAL_ID, -1L)
        val clientRef = intent.getStringExtra(EXTRA_CLIENT_REFERENCE)
        val saleId = intent.getLongExtra(EXTRA_SALE_ID, -1L)
        when {
            localId > 0L -> viewModel.loadLocalById(localId)
            !clientRef.isNullOrBlank() -> viewModel.loadLocalByReference(clientRef)
            saleId > 0L -> viewModel.loadServerSale(saleId, intent.getStringExtra(EXTRA_CLIENT_REFERENCE))
            else -> binding.textReceiptState.text = getString(R.string.receipt_missing_sale)
        }
    }

    private fun render(state: ReceiptViewModel.UiState) {
        when (state) {
            ReceiptViewModel.UiState.Loading -> {
                binding.progress.visibility = View.VISIBLE
                binding.buttonPrint.isEnabled = false
            }
            is ReceiptViewModel.UiState.Ready -> {
                binding.progress.visibility = View.GONE
                bind(state.projection, state.printable)
            }
            is ReceiptViewModel.UiState.Error -> {
                binding.progress.visibility = View.GONE
                binding.buttonPrint.isEnabled = false
                binding.textReceiptState.text = state.message
                binding.textReceiptState.setTextColor(
                    ContextCompat.getColor(this, R.color.status_danger_fg),
                )
            }
        }
    }

    private fun bind(projection: ReceiptProjection, printable: Boolean) {
        val badge = ReceiptStateDisplay.badge(projection.state)
        binding.textReceiptState.setText(badge.labelRes)
        binding.textReceiptState.setTextColor(ContextCompat.getColor(this, badge.colorRes))

        binding.textBusiness.text = projection.businessName
            ?: getString(R.string.receipt_business_fallback)
        binding.textOutletCashier.text = outletCashierLine(projection)
        binding.textInvoice.text = getString(
            R.string.receipt_reference_label,
            projection.reference ?: RupiahMoney.UNAVAILABLE,
        )
        binding.textDate.text = projection.dateTime ?: RupiahMoney.UNAVAILABLE

        renderItems(projection)

        binding.textSubtotal.text = projection.subtotalLabel
        toggleMoneyRow(binding.rowDiscount, binding.textDiscount, projection.discountTotal, projection.discountLabel)
        toggleMoneyRow(binding.rowTax, binding.textTax, projection.taxTotal, projection.taxLabel)
        binding.textGrandTotal.text = projection.grandTotalLabel
        binding.textTender.text = projection.tenderLabel
        binding.textChange.text = projection.changeLabel
        binding.textPaymentMethod.text =
            getString(R.string.receipt_payment_method, projection.paymentMethod)

        renderNote(projection, printable)

        // Print is available only for a backend-approved receipt (UIX8C-R191): a
        // pending offline draft is shown truthfully but not printed until synced.
        binding.buttonPrint.isEnabled = printable
    }

    private fun renderItems(projection: ReceiptProjection) {
        val container = binding.containerItems
        container.removeAllViews()
        projection.lines.forEach { line ->
            val row = ItemReceiptLineBinding.inflate(layoutInflater, container, false)
            row.textLineName.text = line.productName
            row.textLineQtyPrice.text =
                getString(R.string.receipt_qty_price, line.quantity, line.unitPriceLabel)
            row.textLineTotal.text = line.lineTotalLabel
            container.addView(row.root)
        }
    }

    private fun renderNote(projection: ReceiptProjection, printable: Boolean) {
        val note = when {
            projection.state == ReceiptTransactionState.OFFLINE_PENDING ->
                getString(R.string.receipt_note_pending)
            projection.state == ReceiptTransactionState.SYNCING ->
                getString(R.string.receipt_note_syncing)
            projection.state == ReceiptTransactionState.FAILED ->
                getString(R.string.receipt_note_failed)
            projection.state == ReceiptTransactionState.CONFLICT ->
                getString(R.string.receipt_note_conflict)
            projection.isServerAcknowledgedButNotPrintable(printable) ->
                getString(R.string.receipt_note_not_printable)
            else -> null
        }
        if (note == null) {
            binding.textNote.visibility = View.GONE
        } else {
            binding.textNote.text = note
            binding.textNote.visibility = View.VISIBLE
        }
    }

    private fun ReceiptProjection.isServerAcknowledgedButNotPrintable(printable: Boolean): Boolean =
        state.isServerAcknowledged && !printable

    private fun outletCashierLine(projection: ReceiptProjection): String {
        val outlet = projection.outletName ?: RupiahMoney.UNAVAILABLE
        val cashier = projection.cashierName ?: RupiahMoney.UNAVAILABLE
        return getString(R.string.receipt_outlet_cashier, outlet, cashier)
    }

    private fun toggleMoneyRow(row: View, valueView: android.widget.TextView, amount: Long, label: String) {
        if (amount > 0L) {
            row.visibility = View.VISIBLE
            valueView.text = label
        } else {
            row.visibility = View.GONE
        }
    }

    private fun announcePrint(outcome: PrintOutcome) {
        val message = when (outcome) {
            PrintOutcome.Printed -> getString(R.string.receipt_print_sent)
            PrintOutcome.AlreadyPrinting -> getString(R.string.receipt_print_busy)
            is PrintOutcome.Failed -> outcome.message
        }
        Toast.makeText(this, message, Toast.LENGTH_LONG).show()
        binding.textReceiptState.announceForAccessibility(message)
    }

    companion object {
        const val EXTRA_SALE_ID = "extra_sale_id"
        const val EXTRA_CLIENT_REFERENCE = "extra_client_reference"
        const val EXTRA_LOCAL_ID = "extra_local_id"

        /** Open the receipt for an online-acknowledged sale (server sale id). */
        fun forServerSale(context: Context, saleId: Long, clientReference: String? = null): Intent =
            Intent(context, ReceiptActivity::class.java)
                .putExtra(EXTRA_SALE_ID, saleId)
                .apply { clientReference?.let { putExtra(EXTRA_CLIENT_REFERENCE, it) } }

        /** Open the truthful offline/pending receipt for a just-saved transaction. */
        fun forOfflineReference(context: Context, clientReference: String): Intent =
            Intent(context, ReceiptActivity::class.java)
                .putExtra(EXTRA_CLIENT_REFERENCE, clientReference)

        /** Reopen a durable local transaction from history (detail/reprint). */
        fun forLocalTransaction(context: Context, localId: Long): Intent =
            Intent(context, ReceiptActivity::class.java)
                .putExtra(EXTRA_LOCAL_ID, localId)
    }
}
