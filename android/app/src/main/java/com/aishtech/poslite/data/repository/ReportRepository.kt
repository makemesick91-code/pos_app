package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.DailySalesReportDto
import com.aishtech.poslite.data.remote.dto.InventoryMovementSummaryItemDto
import com.aishtech.poslite.data.remote.dto.PaymentSummaryItemDto

/**
 * Read-only access to the backend report summaries (Sprint 9). The backend is
 * the sole authority for report totals; this repository never computes or
 * caches an authoritative figure — it only fetches summaries for lightweight
 * display.
 */
class ReportRepository(private val api: PosApiService) {

    suspend fun getDailySales(storeId: Long? = null): ResultState<DailySalesReportDto> {
        return try {
            val response = api.getDailySalesReport(storeId = storeId)
            val body = response.body()
            if (response.isSuccessful && body?.data != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat ringkasan penjualan (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat ringkasan. Periksa koneksi.")
        }
    }

    suspend fun getPaymentSummary(storeId: Long? = null): ResultState<List<PaymentSummaryItemDto>> {
        return try {
            val response = api.getPaymentSummary(storeId = storeId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat ringkasan pembayaran (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat ringkasan pembayaran. Periksa koneksi.")
        }
    }

    suspend fun getInventoryMovementsSummary(
        storeId: Long? = null,
    ): ResultState<List<InventoryMovementSummaryItemDto>> {
        return try {
            val response = api.getInventoryMovementsSummary(storeId = storeId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat ringkasan stok (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat ringkasan stok. Periksa koneksi.")
        }
    }
}
