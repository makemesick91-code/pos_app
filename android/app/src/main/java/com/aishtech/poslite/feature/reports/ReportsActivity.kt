package com.aishtech.poslite.feature.reports

import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.databinding.ActivityReportsBinding

/**
 * Lightweight daily summary screen (Sprint 9). It shows the backend-computed
 * daily sales/payment/inventory summaries and lets the cashier close the day.
 * It is deliberately summary-only — no charts, no PDF/Excel, no owner dashboard —
 * and never recomputes an authoritative total.
 */
class ReportsActivity : AppCompatActivity() {

    private lateinit var binding: ActivityReportsBinding
    private lateinit var viewModel: ReportsViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityReportsBinding.inflate(layoutInflater)
        setContentView(binding.root)

        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    ReportsViewModel(
                        reports = ServiceLocator.reportRepository(applicationContext),
                        closings = ServiceLocator.closingRepository(applicationContext),
                    ) as T
            },
        )[ReportsViewModel::class.java]

        binding.textDate.text = getString(R.string.reports_date, viewModel.businessDate)
        binding.buttonRefresh.setOnClickListener { viewModel.refresh() }
        binding.buttonClose.setOnClickListener { viewModel.closeToday() }

        viewModel.state.observe(this) { render(it) }
        viewModel.closing.observe(this) { closing ->
            binding.buttonClose.isEnabled = !closing
        }
        viewModel.closingMessage.observe(this) { message ->
            if (message != null) {
                Toast.makeText(this, message, Toast.LENGTH_LONG).show()
                viewModel.consumeClosingMessage()
            }
        }

        viewModel.refresh()
    }

    private fun render(state: ReportsViewModel.UiState) {
        when (state) {
            is ReportsViewModel.UiState.Loading -> {
                binding.progress.visibility = View.VISIBLE
                binding.textError.visibility = View.GONE
                binding.summaryCards.visibility = View.GONE
            }
            is ReportsViewModel.UiState.Error -> {
                binding.progress.visibility = View.GONE
                binding.summaryCards.visibility = View.GONE
                binding.textError.visibility = View.VISIBLE
                binding.textError.text = state.message
            }
            is ReportsViewModel.UiState.Ready -> {
                binding.progress.visibility = View.GONE
                binding.textError.visibility = View.GONE
                binding.summaryCards.visibility = View.VISIBLE
                bind(state.summary)
            }
        }
    }

    private fun bind(summary: ReportsViewModel.Summary) {
        val sales = summary.sales
        binding.textSalesCount.text =
            getString(R.string.reports_sales_count, ReportDisplay.count(sales.salesCount))
        binding.textCancelledCount.text =
            getString(R.string.reports_cancelled_count, ReportDisplay.count(sales.cancelledSalesCount))
        binding.textCashTotal.text = getString(
            R.string.reports_cash_total,
            ReportDisplay.money(ReportDisplay.paidTotalForMethod(summary.payments, ReportDisplay.METHOD_CASH)),
        )
        binding.textQrisTotal.text = getString(
            R.string.reports_qris_total,
            ReportDisplay.money(ReportDisplay.paidTotalForMethod(summary.payments, ReportDisplay.METHOD_QRIS)),
        )
        binding.textGrandTotal.text =
            getString(R.string.reports_grand_total, ReportDisplay.money(sales.grandTotal))
        binding.textSaleOutQty.text = getString(
            R.string.reports_sale_out_qty,
            ReportDisplay.text(ReportDisplay.saleOutQty(summary.inventory)),
        )
        binding.textClosingStatus.text = getString(R.string.reports_closing_hint)
    }
}
