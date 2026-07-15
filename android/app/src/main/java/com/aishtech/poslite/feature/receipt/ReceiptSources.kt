package com.aishtech.poslite.feature.receipt

import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.ReceiptDto
import com.aishtech.poslite.data.repository.OfflineSaleRepository

/**
 * UIX-8C-06 — narrow read seams for the receipt ViewModel so it is unit-testable
 * on the JVM with hand fakes (no Retrofit, no Room). The concrete repositories
 * already satisfy these signatures; the interfaces add no behaviour and no second
 * data path.
 */

/** Backend-approved receipt for an acknowledged sale (implemented by ReceiptRepository). */
interface ServerReceiptSource {
    suspend fun getReceipt(saleId: Long): ResultState<ReceiptDto>
}

/** Durable local transaction reads (implemented by OfflineSaleRepository). */
interface LocalReceiptSource {
    suspend fun findSaleWithItems(localId: Long): OfflineSaleRepository.LocalSaleWithItems?
    suspend fun findSaleWithItemsByReference(clientReference: String): OfflineSaleRepository.LocalSaleWithItems?
}
