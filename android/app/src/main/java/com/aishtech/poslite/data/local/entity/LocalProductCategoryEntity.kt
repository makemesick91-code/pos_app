package com.aishtech.poslite.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/** Locally cached product category row (Room). */
@Entity(tableName = "product_categories")
data class LocalProductCategoryEntity(
    @PrimaryKey val id: Long,
    val name: String,
    val sortOrder: Int,
    val isActive: Boolean,
    val updatedAt: String?,
    val lastSyncedAt: Long,
)
