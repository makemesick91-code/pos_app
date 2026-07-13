package com.aishtech.poslite

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.data.local.OfflineSyncStatus
import com.aishtech.poslite.data.local.dao.OfflineSaleDao
import com.aishtech.poslite.data.local.dao.OfflineSaleItemDao
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleEntity
import com.aishtech.poslite.data.local.entity.LocalOfflineSaleItemEntity
import com.aishtech.poslite.data.remote.dto.CategorySyncResponse
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.CreateSaleRequestDto
import com.aishtech.poslite.data.remote.dto.LoginRequest
import com.aishtech.poslite.data.remote.dto.LoginResponse
import com.aishtech.poslite.data.remote.dto.MeResponse
import com.aishtech.poslite.data.remote.dto.ProductSyncResponse
import com.aishtech.poslite.data.remote.dto.QrisPaymentResponse
import com.aishtech.poslite.data.remote.dto.MetaDto
import com.aishtech.poslite.data.remote.dto.ReceiptResponseDto
import com.aishtech.poslite.data.remote.dto.SaleDto
import com.aishtech.poslite.data.remote.dto.SaleResponse
import retrofit2.Response

/** A minimal server SaleDto for offline-sync tests. */
fun sampleSale(
    id: Long = 42L,
    invoiceNumber: String = "POS-A1-20260707-000042",
    syncStatus: String = "SYNCED",
): SaleDto = SaleDto(
    id = id,
    storeId = 1,
    invoiceNumber = invoiceNumber,
    saleDate = null,
    subtotal = "20000.00",
    discountTotal = "0.00",
    taxTotal = "0.00",
    grandTotal = "20000.00",
    paidTotal = "25000.00",
    changeTotal = "5000.00",
    paymentStatus = "PAID",
    syncStatus = syncStatus,
    source = "ANDROID_OFFLINE",
)

/** SaleResponse for an idempotent replay (meta.idempotent_replay = true). */
fun replaySale(id: Long = 42L): SaleResponse =
    SaleResponse(data = sampleSale(id = id), meta = MetaDto(null, null, null, "POS_ANDROID_SAAS_FOUNDATION", true))

/**
 * In-memory stand-in for the Room offline DAOs (Sprint 7). Because OfflineSaleDao
 * is an abstract class whose only concrete method composes the two @Insert calls,
 * we can subclass it here and exercise the real repository logic with no Room
 * runtime. Implements OfflineSaleItemDao too so one instance backs both.
 */
class FakeOfflineDb : OfflineSaleDao(), OfflineSaleItemDao {

    val sales = LinkedHashMap<Long, LocalOfflineSaleEntity>()
    val items = mutableListOf<LocalOfflineSaleItemEntity>()
    private var saleSeq = 0L
    private var itemSeq = 0L

    override suspend fun insertSale(sale: LocalOfflineSaleEntity): Long {
        val id = ++saleSeq
        sales[id] = sale.copy(localId = id)
        return id
    }

    override suspend fun insertItems(items: List<LocalOfflineSaleItemEntity>) {
        items.forEach { this.items.add(it.copy(localId = ++itemSeq)) }
    }

    override suspend fun getPendingOrFailed(limit: Int): List<LocalOfflineSaleEntity> =
        sales.values
            .filter {
                it.syncStatus == OfflineSyncStatus.PENDING ||
                    it.syncStatus == OfflineSyncStatus.FAILED ||
                    // UIX7-R009/R012 — recover orphaned in-flight (crashed mid-attempt) rows.
                    it.syncStatus == OfflineSyncStatus.SYNCING
            }
            .sortedBy { it.createdAt }
            .take(limit)

    override suspend fun getOfflineSaleWithItems(localId: Long): LocalOfflineSaleEntity? = sales[localId]

    override suspend fun markSyncing(localId: Long, attemptedAt: Long) {
        sales[localId]?.let {
            sales[localId] = it.copy(syncStatus = OfflineSyncStatus.SYNCING, lastAttemptedAt = attemptedAt)
        }
    }

    override suspend fun markSynced(localId: Long, serverSaleId: Long, invoiceNumber: String?, syncedAt: Long) {
        sales[localId]?.let {
            sales[localId] = it.copy(
                syncStatus = OfflineSyncStatus.SYNCED,
                serverSaleId = serverSaleId,
                serverInvoiceNumber = invoiceNumber,
                syncedAt = syncedAt,
                lastSyncError = null,
            )
        }
    }

    override suspend fun markFailed(localId: Long, error: String?, attemptedAt: Long) {
        sales[localId]?.let {
            sales[localId] = it.copy(
                syncStatus = OfflineSyncStatus.FAILED,
                syncAttemptCount = it.syncAttemptCount + 1,
                lastSyncError = error,
                lastAttemptedAt = attemptedAt,
            )
        }
    }

    override suspend fun markConflict(localId: Long, error: String?, attemptedAt: Long) {
        sales[localId]?.let {
            sales[localId] = it.copy(
                syncStatus = OfflineSyncStatus.CONFLICT,
                syncAttemptCount = it.syncAttemptCount + 1,
                lastSyncError = error,
                lastAttemptedAt = attemptedAt,
            )
        }
    }

    override suspend fun countPending(): Int =
        sales.values.count {
            it.syncStatus == OfflineSyncStatus.PENDING || it.syncStatus == OfflineSyncStatus.SYNCING
        }

    override suspend fun countFailed(): Int =
        sales.values.count { it.syncStatus == OfflineSyncStatus.FAILED }

    override suspend fun getItemsForSale(offlineSaleLocalId: Long): List<LocalOfflineSaleItemEntity> =
        items.filter { it.offlineSaleLocalId == offlineSaleLocalId }
}

/**
 * Configurable PosApiService fake for offline-sync tests. Only createSale is
 * driven; it captures each request and returns the queued responses in order
 * (falling back to the last one). All other endpoints are unused.
 */
class FakeSyncApi(
    private val responses: List<Response<SaleResponse>>,
) : PosApiService {

    val capturedRequests = mutableListOf<CreateSaleRequestDto>()
    private var index = 0

    override suspend fun createSale(request: CreateSaleRequestDto): Response<SaleResponse> {
        capturedRequests.add(request)
        val response = responses[minOf(index, responses.lastIndex)]
        index++
        return response
    }

    override suspend fun login(request: LoginRequest): Response<LoginResponse> = error("unused")
    override suspend fun me(): Response<MeResponse> = error("unused")
    override suspend fun logout(): Response<Unit> = error("unused")
    override suspend fun syncProducts(updatedSince: String?, storeId: Long?): Response<ProductSyncResponse> = error("unused")
    override suspend fun syncCategories(updatedSince: String?, storeId: Long?): Response<CategorySyncResponse> = error("unused")
    override suspend fun getSale(id: Long): Response<SaleResponse> = error("unused")
    override suspend fun cancelSale(id: Long): Response<SaleResponse> = error("unused")
    override suspend fun createQrisPayment(saleId: Long, request: CreateQrisPaymentRequestDto): Response<QrisPaymentResponse> = error("unused")
    override suspend fun getPaymentStatus(paymentId: Long): Response<QrisPaymentResponse> = error("unused")
    override suspend fun getReceipt(saleId: Long): Response<ReceiptResponseDto> = error("unused")
    override suspend fun getCurrentStock(storeId: Long?, query: String?, limit: Int?): Response<com.aishtech.poslite.data.remote.dto.CurrentStockResponseDto> = error("unused")
    override suspend fun getProductStock(productId: Long): Response<com.aishtech.poslite.data.remote.dto.ProductStockResponseDto> = error("unused")
    override suspend fun getDailySalesReport(storeId: Long?, date: String?, cashierId: Long?): Response<com.aishtech.poslite.data.remote.dto.DailySalesReportResponseDto> = error("unused")
    override suspend fun getPaymentSummary(storeId: Long?, date: String?): Response<com.aishtech.poslite.data.remote.dto.PaymentSummaryResponseDto> = error("unused")
    override suspend fun getInventoryMovementsSummary(storeId: Long?, date: String?): Response<com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryResponseDto> = error("unused")
    override suspend fun createDailyClosing(request: com.aishtech.poslite.data.remote.dto.CreateDailyClosingRequestDto): Response<com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto> = error("unused")
    override suspend fun getDailyClosings(storeId: Long?): Response<com.aishtech.poslite.data.remote.dto.DailyClosingListResponseDto> = error("unused")
    override suspend fun getDailyClosing(id: Long): Response<com.aishtech.poslite.data.remote.dto.DailyClosingResponseDto> = error("unused")
    override suspend fun getSubscriptionStatus(): Response<com.aishtech.poslite.data.remote.dto.SubscriptionStatusResponseDto> = error("unused")
    override suspend fun registerDevice(request: com.aishtech.poslite.data.remote.dto.RegisterDeviceRequestDto): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")
    override suspend fun deviceHeartbeat(request: com.aishtech.poslite.data.remote.dto.DeviceHeartbeatRequestDto): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")
    override suspend fun listDevices(status: String?): Response<com.aishtech.poslite.data.remote.dto.DeviceListResponseDto> = error("unused")
    override suspend fun revokeDevice(deviceId: Long): Response<com.aishtech.poslite.data.remote.dto.RegisteredDeviceResponseDto> = error("unused")
    override suspend fun activateDevice(request: com.aishtech.poslite.data.remote.dto.ActivateDeviceRequestDto): Response<com.aishtech.poslite.data.remote.dto.DeviceActivationResponseDto> = error("unused")
    override suspend fun androidDeviceHeartbeat(): Response<com.aishtech.poslite.data.remote.dto.DeviceActivationResponseDto> = error("unused")
    override suspend fun getAndroidRuntimePolicy(): Response<com.aishtech.poslite.data.remote.dto.AndroidRuntimePolicyResponseDto> = error("unused")
    override suspend fun submitSyncBatch(request: com.aishtech.poslite.data.remote.dto.SyncBatchRequestDto): Response<com.aishtech.poslite.data.remote.dto.SyncBatchResponseDto> = error("unused")
}
