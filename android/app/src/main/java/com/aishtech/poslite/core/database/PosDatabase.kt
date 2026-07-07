package com.aishtech.poslite.core.database

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.TypeConverters
import com.aishtech.poslite.data.local.dao.AppSettingDao
import com.aishtech.poslite.data.local.dao.OfflineSaleDao
import com.aishtech.poslite.data.local.dao.OfflineSaleItemDao
import com.aishtech.poslite.data.local.dao.ProductCategoryDao
import com.aishtech.poslite.data.local.dao.ProductDao
import com.aishtech.poslite.data.local.entity.AppSettingEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.local.entity.LocalProductCategoryEntity
import com.aishtech.poslite.data.local.entity.LocalProductEntity

/**
 * Room database for the offline product/category catalog (Sprint 3) and, since
 * Sprint 7, the offline CASH sale sync queue (offline_sales / offline_sale_items).
 */
@Database(
    entities = [
        LocalProductEntity::class,
        LocalProductCategoryEntity::class,
        AppSettingEntity::class,
        LocalOfflineSaleEntity::class,
        LocalOfflineSaleItemEntity::class,
    ],
    version = 2,
    exportSchema = false,
)
@TypeConverters(Converters::class)
abstract class PosDatabase : RoomDatabase() {

    abstract fun productDao(): ProductDao
    abstract fun productCategoryDao(): ProductCategoryDao
    abstract fun appSettingDao(): AppSettingDao
    abstract fun offlineSaleDao(): OfflineSaleDao
    abstract fun offlineSaleItemDao(): OfflineSaleItemDao

    companion object {
        @Volatile
        private var INSTANCE: PosDatabase? = null

        fun getInstance(context: Context): PosDatabase =
            INSTANCE ?: synchronized(this) {
                INSTANCE ?: Room.databaseBuilder(
                    context.applicationContext,
                    PosDatabase::class.java,
                    "aish_pos_catalog.db",
                ).fallbackToDestructiveMigration().build().also { INSTANCE = it }
            }
    }
}
