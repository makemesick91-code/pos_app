package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity

/**
 * One selectable chip in the cashier category filter (UIX8C-R074/R075). The
 * "Semua" (all) chip is modelled as [id] == null so clearing the filter is a
 * first-class, testable state rather than a magic sentinel value. [selected] is
 * carried in the model so the adapter renders selection with text + state, never
 * colour alone (UIX8C-R047/R071).
 */
data class CategoryOption(
    val id: Long?,
    val name: String,
    val selected: Boolean,
) {
    val isAll: Boolean get() = id == null

    companion object {
        /**
         * Build the chip row from the canonical active categories, prepending the
         * "all" chip and marking exactly one chip selected. Pure and
         * side-effect-free so it is unit-testable without the Room stack.
         */
        fun build(
            categories: List<LocalProductCategoryEntity>,
            selectedId: Long?,
            allLabel: String,
        ): List<CategoryOption> {
            val all = CategoryOption(id = null, name = allLabel, selected = selectedId == null)
            val rest = categories.map { category ->
                CategoryOption(
                    id = category.id,
                    name = category.name,
                    selected = selectedId != null && selectedId == category.id,
                )
            }
            return listOf(all) + rest
        }
    }
}
