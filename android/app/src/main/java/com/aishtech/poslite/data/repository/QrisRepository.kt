package com.aishtech.poslite.data.repository

import com.aishtech.poslite.core.network.PosApiService
import com.aishtech.poslite.core.util.ResultState
import com.aishtech.poslite.data.remote.dto.CreateQrisPaymentRequestDto
import com.aishtech.poslite.data.remote.dto.QrisPaymentDto

/**
 * Consumes the Sprint 5 backend-driven QRIS API: request a QRIS payment for a
 * sale, then poll its status. The repository holds no payment gateway
 * credentials and never calls a gateway directly — every call goes to the Aish
 * POS backend, which owns the gateway integration.
 */
class QrisRepository(private val api: PosApiService) {

    suspend fun createQrisPayment(saleId: Long, provider: String? = null): ResultState<QrisPaymentDto> {
        return try {
            val response = api.createQrisPayment(saleId, CreateQrisPaymentRequestDto(provider = provider))
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else if (response.code() == 422) {
                ResultState.Error("QRIS tidak dapat dibuat untuk penjualan ini.")
            } else {
                ResultState.Error("Gagal membuat QRIS (kode ${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error("Tidak dapat terhubung ke server.")
        }
    }

    suspend fun getPaymentStatus(paymentId: Long): ResultState<QrisPaymentDto> {
        return try {
            val response = api.getPaymentStatus(paymentId)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                ResultState.Success(body.data)
            } else {
                ResultState.Error("Gagal memuat status (kode ${response.code()}).")
            }
        } catch (e: Exception) {
            ResultState.Error("Tidak dapat terhubung ke server.")
        }
    }
}
