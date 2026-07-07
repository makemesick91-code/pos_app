package com.aishtech.poslite.feature.receipt

import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.databinding.ActivityReceiptBinding

/**
 * Receipt foundation screen (Sprint 6). Launched with a sale id; it loads the
 * backend-approved receipt, shows a lightweight text preview, and prints via the
 * Bluetooth foundation.
 *
 * The print button is disabled whenever the backend marks the receipt
 * non-printable (unpaid / pending QRIS / cancelled / expired / failed), and the
 * block reason is shown instead. It never talks to a payment gateway or holds a
 * credential.
 */
class ReceiptActivity : AppCompatActivity() {

    private lateinit var binding: ActivityReceiptBinding
    private lateinit var viewModel: ReceiptViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityReceiptBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val saleId = intent.getLongExtra(EXTRA_SALE_ID, -1L)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ReceiptViewModel(
                        receipts = ServiceLocator.receiptRepository(applicationContext),
                        printer = ServiceLocator.printerRepository(applicationContext),
                    ) as T
            },
        )[ReceiptViewModel::class.java]

        binding.buttonPrint.isEnabled = false
        binding.buttonPrint.setOnClickListener { viewModel.print() }

        viewModel.state.observe(this) { render(it) }
        viewModel.printMessage.observe(this) { message ->
            if (message != null) {
                Toast.makeText(this, message, Toast.LENGTH_LONG).show()
                viewModel.consumePrintMessage()
            }
        }

        if (saleId <= 0L) {
            binding.textReceiptStatus.text = getString(R.string.receipt_missing_sale)
        } else {
            viewModel.load(saleId)
        }
    }

    private fun render(state: ReceiptViewModel.UiState) {
        when (state) {
            is ReceiptViewModel.UiState.Loading -> {
                binding.progress.visibility = View.VISIBLE
                binding.buttonPrint.isEnabled = false
            }
            is ReceiptViewModel.UiState.Ready -> {
                binding.progress.visibility = View.GONE
                val receipt = state.receipt

                binding.textInvoice.text =
                    getString(R.string.receipt_invoice_label, receipt.invoiceNumber ?: "-")
                binding.textReceiptStatus.text =
                    getString(R.string.receipt_status_label, receipt.receiptStatus ?: "-")
                binding.textPreview.text = state.previewText

                if (receipt.printable) {
                    binding.buttonPrint.isEnabled = true
                    binding.textBlockReason.visibility = View.GONE
                } else {
                    binding.buttonPrint.isEnabled = false
                    binding.textBlockReason.visibility = View.VISIBLE
                    binding.textBlockReason.text =
                        receipt.printBlockReason ?: getString(R.string.receipt_not_printable)
                }
            }
            is ReceiptViewModel.UiState.Error -> {
                binding.progress.visibility = View.GONE
                binding.buttonPrint.isEnabled = false
                binding.textReceiptStatus.text = state.message
            }
        }
    }

    companion object {
        const val EXTRA_SALE_ID = "extra_sale_id"
    }
}
