package com.aishtech.poslite

import com.aishtech.poslite.data.local.CatalogMappers
import com.aishtech.poslite.data.remote.dto.ProductDto
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Pure-JVM tests for DTO -> Room entity mapping (Sprint 3 catalog sync).
 */
class CatalogMappingTest {

    private fun product(
        id: Long = 1L,
        selling: String? = "10000.00",
        effective: String? = "10000.00",
        active: Boolean = true,
    ) = ProductDto(
        id = id,
        categoryId = 7L,
        sku = "SKU-$id",
        barcode = "899$id",
        name = "  Kopi  ",
        unit = "pcs",
        sellingPrice = selling,
        effectiveSellingPrice = effective,
        isStockTracked = true,
        isActive = active,
        updatedAt = "2026-07-07T00:00:00Z",
    )

    @Test
    fun `product dto maps to local entity`() {
        val entity = CatalogMappers.toEntity(product(), syncedAt = 123L)

        assertEquals(1L, entity.id)
        assertEquals(7L, entity.categoryId)
        assertEquals("SKU-1", entity.sku)
        assertEquals("Kopi", entity.name) // trimmed
        assertEquals(10000.0, entity.sellingPrice, 0.001)
        assertEquals(10000.0, entity.effectiveSellingPrice, 0.001)
        assertEquals(123L, entity.lastSyncedAt)
        assertTrue(entity.isStockTracked)
    }

    @Test
    fun `effective price falls back to selling price when null`() {
        val entity = CatalogMappers.toEntity(
            product(selling = "15000.00", effective = null),
            syncedAt = 1L,
        )

        assertEquals(15000.0, entity.sellingPrice, 0.001)
        assertEquals(15000.0, entity.effectiveSellingPrice, 0.001)
    }

    @Test
    fun `malformed price parses to zero without crashing`() {
        val entity = CatalogMappers.toEntity(
            product(selling = "not-a-number", effective = null),
            syncedAt = 1L,
        )

        assertEquals(0.0, entity.sellingPrice, 0.001)
        assertEquals(0.0, entity.effectiveSellingPrice, 0.001)
    }

    @Test
    fun `inactive product is preserved as inactive when mapped`() {
        // Inactive rows are still cached (preserved) but the active-search DAO
        // contract filters them out (isActive = 1).
        val entity = CatalogMappers.toEntity(product(active = false), syncedAt = 1L)

        assertFalse(entity.isActive)
    }
}
