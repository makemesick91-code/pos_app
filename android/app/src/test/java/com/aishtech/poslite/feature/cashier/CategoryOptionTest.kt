package com.aishtech.poslite.feature.cashier

import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * UIX-8C-03 — the category chip row (UIX8C-R074/R075) always leads with an "all"
 * chip, marks exactly one chip selected, and models "all" as id == null so
 * clearing the filter is a first-class state, never a magic value.
 */
class CategoryOptionTest {

    private fun cat(id: Long, name: String) =
        LocalProductCategoryEntity(id = id, name = name, sortOrder = 0, isActive = true, updatedAt = null, lastSyncedAt = 0)

    private val categories = listOf(cat(1, "Minuman"), cat(2, "Makanan"))

    @Test
    fun buildPrependsAllChipSelectedByDefault() {
        val options = CategoryOption.build(categories, selectedId = null, allLabel = "Semua")
        assertEquals(3, options.size)
        assertEquals("Semua", options.first().name)
        assertTrue(options.first().isAll)
        assertTrue("all chip selected when no category selected", options.first().selected)
        assertEquals(1, options.count { it.selected })
    }

    @Test
    fun buildMarksExactlyOneSelectedCategory() {
        val options = CategoryOption.build(categories, selectedId = 2, allLabel = "Semua")
        assertEquals(1, options.count { it.selected })
        val selected = options.single { it.selected }
        assertEquals(2L, selected.id)
        assertEquals("Makanan", selected.name)
        assertFalse("all chip is not selected when a category is active", options.first().selected)
    }

    @Test
    fun emptyCategoriesStillHasAllChip() {
        val options = CategoryOption.build(emptyList(), selectedId = null, allLabel = "Semua")
        assertEquals(1, options.size)
        assertTrue(options.single().isAll)
        assertTrue(options.single().selected)
    }

    @Test
    fun selectedIdWithNoMatchingCategoryLeavesNoneSelected() {
        // Defensive: a stale selection id that no longer exists selects nothing
        // rather than falsely highlighting "all".
        val options = CategoryOption.build(categories, selectedId = 99, allLabel = "Semua")
        assertEquals(0, options.count { it.selected })
    }
}
