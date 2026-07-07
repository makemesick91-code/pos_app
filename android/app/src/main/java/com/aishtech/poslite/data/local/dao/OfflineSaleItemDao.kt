package com.aishtech.poslite.data.local.dao

import androidx.room.Dao
import androidx.room.Query
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity

/**
 * Read access to the snapshotted lines of an offline sale (Sprint 7). Lines are
 * written together with their parent via [OfflineSaleDao.insertOfflineSaleWithItems].
 */
@Dao
interface OfflineSaleItemDao {

    @Query("SELECT * FROM offline_sale_items WHERE offlineSaleLocalId = :offlineSaleLocalId ORDER BY localId ASC")
    suspend fun getItemsForSale(offlineSaleLocalId: Long): List<LocalOfflineSaleItemEntity>
}
