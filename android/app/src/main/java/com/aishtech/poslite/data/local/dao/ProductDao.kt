package com.aishtech.poslite.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.aishtech.poslite.data.local.entity.LocalProductEntity

/**
 * Product catalog DAO. All list/search queries filter to active products and
 * apply a LIMIT so older devices never load the full catalog into memory.
 */
@Dao
interface ProductDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(products: List<LocalProductEntity>)

    @Query(
        """
        SELECT * FROM products
        WHERE isActive = 1
          AND (
            name LIKE '%' || :query || '%'
            OR sku LIKE '%' || :query || '%'
            OR barcode LIKE '%' || :query || '%'
          )
        ORDER BY name
        LIMIT :limit
        """
    )
    suspend fun searchActiveProducts(query: String, limit: Int = 50): List<LocalProductEntity>

    @Query("SELECT * FROM products WHERE isActive = 1 ORDER BY name LIMIT :limit")
    suspend fun getActiveProducts(limit: Int = 200): List<LocalProductEntity>

    @Query("SELECT * FROM products WHERE id = :id LIMIT 1")
    suspend fun findById(id: Long): LocalProductEntity?

    @Query("SELECT COUNT(*) FROM products WHERE isActive = 1")
    suspend fun countActive(): Int
}
