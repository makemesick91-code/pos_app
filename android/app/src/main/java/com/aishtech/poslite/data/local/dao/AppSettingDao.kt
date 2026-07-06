package com.aishtech.poslite.data.local.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import com.aishtech.poslite.data.local.entity.AppSettingEntity

@Dao
interface AppSettingDao {

    @Query("SELECT value FROM app_settings WHERE key = :key LIMIT 1")
    suspend fun getValue(key: String): String?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun put(setting: AppSettingEntity)

    suspend fun setValue(key: String, value: String) {
        put(AppSettingEntity(key = key, value = value, updatedAt = System.currentTimeMillis()))
    }
}
