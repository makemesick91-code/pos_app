package com.aishtech.poslite.feature.history

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.aishtech.poslite.R
import com.aishtech.poslite.core.ServiceLocator
import com.aishtech.poslite.core.money.RupiahMoney
import com.aishtech.poslite.databinding.ActivityTransactionHistoryBinding
import com.aishtech.poslite.databinding.ItemTransactionBinding
import com.aishtech.poslite.feature.receipt.ReceiptActivity

/**
 * UIX-8C-06 — premium transaction-history screen. A read-only, reconciled list
 * with exactly one row per logical transaction and explicit, accessible sync-state
 * badges (text + colour, never colour alone). Tapping a row opens the durable
 * transaction detail / receipt. Business truth stays in the repository/backend.
 */
class TransactionHistoryActivity : AppCompatActivity() {

    private lateinit var binding: ActivityTransactionHistoryBinding
    private lateinit var viewModel: TransactionHistoryViewModel
    private val adapter = TransactionAdapter { row -> openDetail(row) }

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
    }

    override fun onResume() {
        super.onResume()
        // Restore truth from the repository/Room on every entry, so a sale that
        // synced while away is reflected without replaying a stale in-memory event.
        viewModel.load()
    }

    private fun openDetail(row: HistoryRow) {
        val localId = row.localId ?: return
        startActivity(ReceiptActivity.forLocalTransaction(this, localId))
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
                adapter.submitList(state.rows)
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

    private class TransactionAdapter(
        private val onClick: (HistoryRow) -> Unit,
    ) : ListAdapter<HistoryRow, TransactionAdapter.Holder>(DIFF) {

        init {
            setHasStableIds(true)
        }

        override fun getItemId(position: Int): Long =
            getItem(position).localId ?: getItem(position).key.hashCode().toLong()

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): Holder {
            val binding = ItemTransactionBinding.inflate(
                LayoutInflater.from(parent.context), parent, false,
            )
            return Holder(binding, onClick)
        }

        override fun onBindViewHolder(holder: Holder, position: Int) = holder.bind(getItem(position))

        class Holder(
            private val binding: ItemTransactionBinding,
            private val onClick: (HistoryRow) -> Unit,
        ) : RecyclerView.ViewHolder(binding.root) {
            fun bind(row: HistoryRow) {
                val ctx = binding.root.context
                binding.textHistoryTotal.text = RupiahMoney.format(row.grandTotal)
                binding.textHistoryRef.text = row.reference
                    ?.let { ctx.getString(R.string.history_ref, it) }
                    ?: ctx.getString(R.string.history_ref, RupiahMoney.UNAVAILABLE)
                binding.textHistoryDate.text = row.dateTime

                val badge = HistoryStateDisplay.badge(row.displayState)
                binding.textHistoryStatus.setText(badge.labelRes)
                binding.textHistoryStatus.setTextColor(ContextCompat.getColor(ctx, badge.colorRes))

                val stateLabel = ctx.getString(badge.labelRes)
                binding.root.contentDescription = ctx.getString(
                    R.string.cd_history_row,
                    RupiahMoney.format(row.grandTotal),
                    stateLabel,
                )
                binding.root.isClickable = row.localId != null
                binding.root.setOnClickListener { onClick(row) }
            }
        }

        companion object {
            private val DIFF = object : DiffUtil.ItemCallback<HistoryRow>() {
                override fun areItemsTheSame(oldItem: HistoryRow, newItem: HistoryRow): Boolean =
                    oldItem.key == newItem.key

                override fun areContentsTheSame(oldItem: HistoryRow, newItem: HistoryRow): Boolean =
                    oldItem == newItem
            }
        }
    }

    companion object {
        fun intent(context: Context): Intent =
            Intent(context, TransactionHistoryActivity::class.java)
    }
}
