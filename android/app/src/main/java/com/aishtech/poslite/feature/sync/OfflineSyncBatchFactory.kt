package com.aishtech.poslite.feature.sync

import com.aishtech.poslite.data.remote.dto.SyncBatchItemDto
import com.aishtech.poslite.data.remote.dto.SyncBatchRequestDto

/**
 * Sprint 34 — builds a deterministic, idempotent sync batch from queued offline
 * items (ADR-R012/R013/R014).
 *
 * Every item carries a stable client_item_id (the Sprint 7 device-generated
 * clientReference), so a retried submit re-uses the exact same ids and the server
 * dedupes without creating a duplicate sale. The batch id is derived
 * deterministically from the item ids, so re-submitting the SAME queued set
 * produces the SAME client_batch_id — the server treats it as an idempotent replay
 * rather than a new batch.
 */
data class QueuedSyncItem(
    val clientItemId: String,
    val itemType: String,
    val action: String = "create",
)

object OfflineSyncBatchFactory {

    const val MAX_ITEMS_PER_BATCH = 200

    /**
     * Build a batch request for a set of queued items. The batch id is stable for
     * a given (deviceUuid, ordered item ids) set so retries replay idempotently.
     */
    fun build(deviceUuid: String, items: List<QueuedSyncItem>): SyncBatchRequestDto {
        require(items.isNotEmpty()) { "A sync batch must contain at least one item." }
        require(items.size <= MAX_ITEMS_PER_BATCH) { "A sync batch may not exceed $MAX_ITEMS_PER_BATCH items." }

        val orderedIds = items.map { it.clientItemId }.sorted()
        val batchId = deterministicBatchId(deviceUuid, orderedIds)

        return SyncBatchRequestDto(
            clientBatchId = batchId,
            idempotencyKey = batchId,
            items = items.map {
                SyncBatchItemDto(
                    clientItemId = it.clientItemId,
                    itemType = it.itemType,
                    action = it.action,
                )
            },
        )
    }

    /**
     * Stable, non-reversible batch id derived from the device + the ordered item
     * ids. Same inputs → same id (idempotent replay); different set → different id.
     */
    fun deterministicBatchId(deviceUuid: String, orderedItemIds: List<String>): String {
        val seed = (listOf(deviceUuid) + orderedItemIds).joinToString("|")
        // A stable, positive hex digest that is safe to send and never reveals PII.
        val hash = seed.hashCode().toLong() and 0xffffffffL
        return "batch-" + hash.toString(16).padStart(8, '0') + "-" + orderedItemIds.size
    }
}
