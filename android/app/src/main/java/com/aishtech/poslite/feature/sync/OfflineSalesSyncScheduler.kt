package com.aishtech.poslite.feature.sync

import android.content.Context
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import java.util.concurrent.TimeUnit

/**
 * Schedules [OfflineSalesSyncWorker] (Sprint 7). The sync only runs while the
 * device is connected, and retries use exponential backoff so a flaky network
 * never hammers the backend. Enqueues are unique (KEEP) so a burst of offline
 * checkouts collapses into a single pending sync.
 */
object OfflineSalesSyncScheduler {

    const val UNIQUE_WORK_NAME = "offline-sales-sync"
    private const val BACKOFF_SECONDS = 30L

    /** Enqueue a one-shot connected sync, keeping any already-pending request. */
    fun enqueue(context: Context) {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val request = OneTimeWorkRequestBuilder<OfflineSalesSyncWorker>()
            .setConstraints(constraints)
            .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, BACKOFF_SECONDS, TimeUnit.SECONDS)
            .build()

        WorkManager.getInstance(context.applicationContext)
            .enqueueUniqueWork(UNIQUE_WORK_NAME, ExistingWorkPolicy.KEEP, request)
    }
}
