package com.aishtech.poslite.core.database

import androidx.room.TypeConverter

/**
 * Room type converters. Kept minimal for Sprint 3 — the local catalog uses
 * primitive columns, so only nullable helpers are provided for future use.
 */
class Converters {
    @TypeConverter
    fun fromNullableLong(value: Long?): Long = value ?: 0L
}
