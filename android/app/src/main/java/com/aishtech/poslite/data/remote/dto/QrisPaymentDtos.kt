package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * QRIS payment DTOs (Sprint 5). The client may only pick a provider; every money
 * total, the provider reference, the QR payload and the status are backend/
 * gateway-driven. No payment gateway credential is ever present here — Android
 * only ever talks to the Aish POS backend, never a payment gateway directly.
 */

/** POST /api/v1/sales/{sale}/payments/qris request. Provider is optional. */
data class CreateQrisPaymentRequestDto(
    @SerializedName("provider") val provider: String? = null,
)

/** Envelope for both the QRIS create and payment-status endpoints. */
data class QrisPaymentResponse(
    @SerializedName("data") val data: QrisPaymentDto,
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class QrisPaymentDto(
    @SerializedName("id") val id: Long,
    @SerializedName("sale_id") val saleId: Long?,
    @SerializedName("method") val method: String?,
    @SerializedName("provider") val provider: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("amount") val amount: String?,
    @SerializedName("provider_reference") val providerReference: String?,
    @SerializedName("qr_payload") val qrPayload: String?,
    @SerializedName("qr_image_url") val qrImageUrl: String?,
    @SerializedName("payment_url") val paymentUrl: String?,
    @SerializedName("expired_at") val expiredAt: String?,
    @SerializedName("paid_at") val paidAt: String?,
    @SerializedName("sale_payment_status") val salePaymentStatus: String?,
)
