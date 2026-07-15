package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.ReceiptDto

/**
 * Fetches the backend-approved receipt for a sale (Sprint 6 foundation).
 *
 * The repository never computes authoritative totals or decides print
 * eligibility — the backend owns both. It only surfaces the approved
 * [ReceiptDto] (including `printable` / `printBlockReason`) for the UI and the
 * ESC/POS formatter to consume.
 */
class ReceiptRepository(private val api: PosApiService) :
    com.aishtech.poslite.feature.receipt.ServerReceiptSource {

    override suspend fun getReceipt(saleId: Long): ResultState<ReceiptDto> {
        return try {
            val response = api.getReceipt(saleId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else if (response.code() == 404) {
                ResultState.Error("Struk tidak ditemukan untuk penjualan ini.")
            } else {
                ResultState.Error("Gagal memuat struk (kode ${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error("Tidak dapat terhubung ke server.")
        }
    }
}
