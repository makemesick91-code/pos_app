package com.aishtech.poslite.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity

@Dao
interface ProductCategoryDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(categories: List<LocalProductCategoryEntity>)

    @Query("SELECT * FROM product_categories WHERE isActive = 1 ORDER BY sortOrder, name")
    suspend fun getActiveCategories(): List<LocalProductCategoryEntity>

    @Query("SELECT COUNT(*) FROM product_categories WHERE isActive = 1")
    suspend fun countActive(): Int
}
