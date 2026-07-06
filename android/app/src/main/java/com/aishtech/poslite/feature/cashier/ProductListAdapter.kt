package com.aishtech.poslite.feature.cashier

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
 */
class ProductListAdapter(
    private val onAdd: (LocalProductEntity) -> Unit,
) : ListAdapter<LocalProductEntity, ProductListAdapter.ProductViewHolder>(DIFF) {

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
            binding.buttonAdd.setOnClickListener { onAdd(product) }
        }
    }

    private fun formatPrice(value: Double): String {
        val format = NumberFormat.getNumberInstance(Locale("in", "ID"))
        format.maximumFractionDigits = 0
        return "Rp ${format.format(value)}"
    }

    private companion object {
        val DIFF = object : DiffUtil.ItemCallback<LocalProductEntity>() {
            override fun areItemsTheSame(a: LocalProductEntity, b: LocalProductEntity) = a.id == b.id
            override fun areContentsTheSame(a: LocalProductEntity, b: LocalProductEntity) = a == b
        }
    }
}
