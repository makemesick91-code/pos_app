package com.aishtech.poslite.core.util

/**
 * Minimal result wrapper for repository/sync operations so the UI can render
 * loading / success / error without leaking exceptions or tokens.
 */
sealed class ResultState<out T> {
    data object Loading : ResultState<Nothing>()
    data class Success<T>(val data: T) : ResultState<T>()
    data class Error(val message: String) : ResultState<Nothing>()
}
