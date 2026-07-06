package com.aishtech.poslite.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * Simple key/value settings table. Holds the incremental sync cursors
 * (last_products_sync_at, last_categories_sync_at) among other local flags.
 */
@Entity(tableName = "app_settings")
data class AppSettingEntity(
    @PrimaryKey val key: String,
    val value: String,
    val updatedAt: Long,
) {
    companion object {
        const val KEY_LAST_PRODUCTS_SYNC_AT = "last_products_sync_at"
        const val KEY_LAST_CATEGORIES_SYNC_AT = "last_categories_sync_at"
    }
}
