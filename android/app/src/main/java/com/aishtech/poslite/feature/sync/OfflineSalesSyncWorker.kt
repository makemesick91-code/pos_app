package com.aishtech.poslite.feature.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.aishtech.poslite.core.ServiceLocator

/**
 * Background replay of queued offline CASH sales (Sprint 7). Runs only when the
 * network is connected (enforced by the WorkManager constraint in
 * [OfflineSalesSyncScheduler]) and must never crash the app:
 *
 *  - any pending sale still failing → Result.retry() so WorkManager backs off.
 *  - all attempted sales resolved (synced/conflict) → Result.success().
 *  - an unexpected exception → Result.retry() (never a thrown crash).
 *
 * Conflicts are left in the queue as CONFLICT for later manual resolution; they
 * do not, by themselves, force a retry.
 */
class OfflineSalesSyncWorker(
    context: Context,
    params: WorkerParameters,
) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result {
        return try {
            val repository = ServiceLocator.offlineSaleRepository(applicationContext)
            val summary = repository.syncPending(limit = BATCH_SIZE)

            if (summary.failed > 0) {
                Result.retry()
            } else {
                Result.success()
            }
        } catch (e: Exception) {
            // Never propagate — a sync failure must not take down the app.
            Result.retry()
        }
    }

    companion object {
        const val BATCH_SIZE = 10
    }
}
