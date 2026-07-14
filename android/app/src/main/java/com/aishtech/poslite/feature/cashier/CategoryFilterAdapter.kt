package com.aishtech.poslite.feature.cashier

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.aishtech.poslite.R
import com.aishtech.poslite.databinding.ItemCategoryChipBinding

/**
 * Horizontal product-category filter (UIX8C-R074/R075). Selection is rendered
 * with text + semantic colour tokens AND an accessibility state description
 * ("dipilih"), never colour alone (UIX8C-R047/R071). Selecting a chip re-queries
 * the product list only — it never mutates the cart (UIX8C-R074). Uses DiffUtil
 * with a stable key (category id) so re-selection stays cheap (UIX8C-R093).
 */
class CategoryFilterAdapter(
    private val onSelect: (CategoryOption) -> Unit,
) : ListAdapter<CategoryOption, CategoryFilterAdapter.ChipViewHolder>(DIFF) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ChipViewHolder {
        val binding = ItemCategoryChipBinding.inflate(
            LayoutInflater.from(parent.context), parent, false,
        )
        return ChipViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ChipViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ChipViewHolder(
        private val binding: ItemCategoryChipBinding,
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(option: CategoryOption) {
            val chip = binding.chipCategory
            val context = chip.context
            chip.text = option.name
            chip.isSelected = option.selected

            val bg = if (option.selected) R.color.state_online_bg else R.color.bg_subtle
            val fg = if (option.selected) R.color.state_online_fg else R.color.text_secondary
            chip.backgroundTintList = ContextCompat.getColorStateList(context, bg)
            chip.setTextColor(ContextCompat.getColor(context, fg))

            // UIX8C-R047/R071 — selection is never colour-only: expose it to
            // assistive tech and prefix the label so TalkBack announces the state.
            ViewCompat.setStateDescription(
                chip,
                context.getString(
                    if (option.selected) R.string.cd_category_selected else R.string.cd_category_unselected,
                ),
            )
            chip.contentDescription = context.getString(R.string.cd_category_filter) + ": " + option.name

            chip.setOnClickListener { onSelect(option) }
        }
    }

    private companion object {
        val DIFF = object : DiffUtil.ItemCallback<CategoryOption>() {
            override fun areItemsTheSame(a: CategoryOption, b: CategoryOption) = a.id == b.id
            override fun areContentsTheSame(a: CategoryOption, b: CategoryOption) = a == b
        }
    }
}
