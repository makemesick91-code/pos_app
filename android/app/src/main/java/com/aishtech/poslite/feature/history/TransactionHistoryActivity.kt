package com.aishtech.poslite.feature.history

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.databinding.ActivityTransactionHistoryBinding
import com.aishtech.poslite.databinding.ItemTransactionBinding

/**
 * UIX-8B — transaction-history screen. Read-only list of the device's local
 * sales with explicit, accessible sync-state badges. One row per transaction
 * (UIX8B-R059..R064). Business truth stays in the repository/backend.
 */
class TransactionHistoryActivity : AppCompatActivity() {

    private lateinit var binding: ActivityTransactionHistoryBinding
    private lateinit var viewModel: TransactionHistoryViewModel
    private val adapter = TransactionAdapter()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityTransactionHistoryBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val context = applicationContext
        viewModel = ViewModelProvider(
            this,
            object : ViewModelProvider.Factory {
                @Suppress("UNCHECKED_CAST")
                override fun <T : ViewModel> create(modelClass: Class<T>): T =
                    TransactionHistoryViewModel(ServiceLocator.offlineSaleRepository(context)) as T
            },
        )[TransactionHistoryViewModel::class.java]

        binding.listHistory.layoutManager = LinearLayoutManager(this)
        binding.listHistory.adapter = adapter

        viewModel.state.observe(this) { render(it) }
        viewModel.load()
    }

    private fun render(state: TransactionHistoryViewModel.State) {
        val progress = binding.progressHistory
        val empty = binding.textHistoryEmpty
        val list = binding.listHistory
        when (state) {
            TransactionHistoryViewModel.State.Loading -> {
                progress.visibility = View.VISIBLE
                empty.visibility = View.GONE
            }
            is TransactionHistoryViewModel.State.Loaded -> {
                progress.visibility = View.GONE
                empty.visibility = View.GONE
                list.visibility = View.VISIBLE
                adapter.submit(state.items)
            }
            TransactionHistoryViewModel.State.Empty -> {
                progress.visibility = View.GONE
                empty.text = getString(R.string.history_empty)
                empty.visibility = View.VISIBLE
            }
            is TransactionHistoryViewModel.State.Error -> {
                progress.visibility = View.GONE
                empty.text = getString(R.string.history_error)
                empty.visibility = View.VISIBLE
            }
        }
    }

    private class TransactionAdapter : RecyclerView.Adapter<TransactionAdapter.Holder>() {
        private val items = mutableListOf<LocalOfflineSaleEntity>()

        fun submit(newItems: List<LocalOfflineSaleEntity>) {
            items.clear()
            items.addAll(newItems)
            notifyDataSetChanged()
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): Holder {
            val binding = ItemTransactionBinding.inflate(
                LayoutInflater.from(parent.context), parent, false,
            )
            return Holder(binding)
        }

        override fun onBindViewHolder(holder: Holder, position: Int) = holder.bind(items[position])

        override fun getItemCount(): Int = items.size

        // Stable ids from the local primary key (UIX8B-R078).
        override fun getItemId(position: Int): Long = items[position].localId

        class Holder(private val binding: ItemTransactionBinding) :
            RecyclerView.ViewHolder(binding.root) {
            fun bind(sale: LocalOfflineSaleEntity) {
                val ctx = binding.root.context
                // Legacy Double column bridged to the canonical formatter at this
                // single display boundary (UIX8B-R016/R063).
                binding.textHistoryTotal.text =
                    RupiahMoney.format(RupiahMoney.fromDouble(sale.grandTotal))
                val ref = sale.serverInvoiceNumber
                    ?.let { ctx.getString(R.string.history_invoice, it) }
                    ?: ctx.getString(R.string.history_ref, sale.clientReference)
                binding.textHistoryRef.text = ref
                binding.textHistoryDate.text = sale.saleDate

                val badge = SyncStatusDisplay.badge(sale.syncStatus)
                binding.textHistoryStatus.setText(badge.labelRes)
                binding.textHistoryStatus.setTextColor(ContextCompat.getColor(ctx, badge.colorRes))
            }
        }
    }
}
