package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CurrentStockItemDto
import com.aishtech.poslite.data.remote.dto.ProductStockDto

/**
 * Read-only access to backend inventory stock. The backend is the sole
 * authority for stock (derived from the ledger); this repository never mutates
 * or caches an authoritative stock figure — it only fetches for display
 * (Sprint 8). Sales/checkout are never blocked on a stale value here.
 */
class StockRepository(private val api: PosApiService) {

    /** Current stock for the tenant's products in the active store context. */
    suspend fun getCurrentStock(storeId: Long? = null): ResultState<List<CurrentStockItemDto>> {
        return try {
            val response = api.getCurrentStock(storeId = storeId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat stok (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat stok. Periksa koneksi.")
        }
    }

    /** Current stock for a single product. */
    suspend fun getProductStock(productId: Long): ResultState<ProductStockDto> {
        return try {
            val response = api.getProductStock(productId)
            val body = response.body()
            if (response.isSuccessful && body?.data != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat stok produk (${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error(e.message ?: "Gagal memuat stok produk. Periksa koneksi.")
        }
    }
}
