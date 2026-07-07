package com.aishtech.poslite.feature.cashier

import android.graphics.Color
import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.aishtech.poslite.data.local.entity.LocalProductEntity
import com.aishtech.poslite.databinding.ItemProductBinding
import java.text.NumberFormat
import java.util.Locale

/**
 * RecyclerView adapter for the cashier product list. Uses DiffUtil so search
 * updates stay cheap on older devices. Product images are not part of Sprint 3.
 *
 * Sprint 8 — each row shows a lightweight, informational stock label supplied by
 * [stockLabels] (productId -> backend `current_stock` string). Unknown stock
 * renders "Stok: -"; non-positive stock is flagged in a warning colour. The
 * backend remains authoritative — the label never blocks a sale.
 */
class ProductListAdapter(
    private val onAdd: (LocalProductEntity) -> Unit,
) : ListAdapter<LocalProductEntity, ProductListAdapter.ProductViewHolder>(DIFF) {

    /** productId -> backend current_stock string (e.g. "12.00"); missing = unknown. */
    private var stockLabels: Map<Long, String> = emptyMap()

    fun setStockLabels(labels: Map<Long, String>) {
        stockLabels = labels
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ProductViewHolder {
        val binding = ItemProductBinding.inflate(
            LayoutInflater.from(parent.context), parent, false,
        )
        return ProductViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ProductViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ProductViewHolder(
        private val binding: ItemProductBinding,
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(product: LocalProductEntity) {
            binding.textName.text = product.name
            binding.textMeta.text = product.sku ?: product.barcode ?: "-"
            binding.textPrice.text = formatPrice(product.effectiveSellingPrice)

            val stock = stockLabels[product.id]
            binding.textStock.text = StockDisplay.label(stock)
            binding.textStock.setTextColor(
                if (StockDisplay.isWarning(stock)) WARNING_COLOR else DEFAULT_STOCK_COLOR,
            )

            binding.buttonAdd.setOnClickListener { onAdd(product) }
        }
    }

    private fun formatPrice(value: Double): String {
        val format = NumberFormat.getNumberInstance(Locale("in", "ID"))
        format.maximumFractionDigits = 0
        return "Rp ${format.format(value)}"
    }

    private companion object {
        val WARNING_COLOR = Color.parseColor("#C62828")
        val DEFAULT_STOCK_COLOR = Color.parseColor("#616161")

        val DIFF = object : DiffUtil.ItemCallback<LocalProductEntity>() {
            override fun areItemsTheSame(a: LocalProductEntity, b: LocalProductEntity) = a.id == b.id
            override fun areContentsTheSame(a: LocalProductEntity, b: LocalProductEntity) = a == b
        }
    }
}
