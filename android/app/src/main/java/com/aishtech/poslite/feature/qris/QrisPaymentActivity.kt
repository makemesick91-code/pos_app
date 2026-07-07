package com.aishtech.poslite.feature.qris

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.data.remote.dto.QrisPaymentDto
import com.aishtech.poslite.databinding.ActivityQrisPaymentBinding
import com.aishtech.poslite.feature.receipt.ReceiptActivity

/**
 * QRIS payment foundation screen (Sprint 5). Launched with a sale id; it asks
 * the backend to create a QRIS payment, renders the QR payload as text (no heavy
 * QR-image dependency yet), shows status/expiry, and lets the cashier refresh
 * the status. It never talks to a payment gateway or holds any credential.
 */
class QrisPaymentActivity : AppCompatActivity() {

    private lateinit var binding: ActivityQrisPaymentBinding
    private lateinit var viewModel: QrisPaymentViewModel
    private var saleId: Long = -1L

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityQrisPaymentBinding.inflate(layoutInflater)
        setContentView(binding.root)

        saleId = intent.getLongExtra(EXTRA_SALE_ID, -1L)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    QrisPaymentViewModel(
                        ServiceLocator.qrisRepository(applicationContext),
                        ServiceLocator.networkMonitor(applicationContext),
                    ) as T
            },
        )[QrisPaymentViewModel::class.java]

        viewModel.state.observe(this) { render(it) }
        binding.buttonRefresh.setOnClickListener { viewModel.refreshStatus() }

        if (saleId <= 0L) {
            binding.textStatus.text = getString(R.string.qris_missing_sale)
        } else {
            viewModel.start(saleId)
        }
    }

    private fun render(state: QrisPaymentViewModel.UiState) {
        when (state) {
            is QrisPaymentViewModel.UiState.Idle -> setLoading(false)
            is QrisPaymentViewModel.UiState.Loading -> setLoading(true)
            is QrisPaymentViewModel.UiState.Ready -> {
                setLoading(false)
                bind(state.payment)
            }
            is QrisPaymentViewModel.UiState.Error -> {
                setLoading(false)
                binding.textStatus.text = state.message
            }
        }
    }

    private fun bind(payment: QrisPaymentDto) {
        binding.textSaleId.text = getString(R.string.qris_sale_label, payment.saleId ?: 0L)
        binding.textQrPayload.text = payment.qrPayload ?: getString(R.string.qris_no_payload)
        binding.textAmount.text = getString(R.string.qris_amount_label, payment.amount ?: "-")
        binding.textStatus.text = getString(R.string.qris_status_label, payment.status ?: "-")
        binding.textExpiredAt.text = getString(R.string.qris_expired_label, payment.expiredAt ?: "-")

        binding.buttonRefresh.isEnabled = true

        // Sprint 6 — once QRIS is settled, allow opening the (now FINAL) receipt.
        if (payment.status == "PAID" && saleId > 0L) {
            binding.textStatus.text =
                getString(R.string.qris_status_label, payment.status) + "\n" +
                getString(R.string.qris_view_receipt)
            binding.textStatus.setOnClickListener { openReceipt(saleId) }
        } else {
            binding.textStatus.setOnClickListener(null)
        }
    }

    private fun openReceipt(saleId: Long) {
        val intent = Intent(this, ReceiptActivity::class.java)
            .putExtra(ReceiptActivity.EXTRA_SALE_ID, saleId)
        startActivity(intent)
    }

    private fun setLoading(loading: Boolean) {
        binding.progress.visibility = if (loading) View.VISIBLE else View.GONE
        binding.buttonRefresh.isEnabled = !loading
    }

    companion object {
        const val EXTRA_SALE_ID = "extra_sale_id"
    }
}
