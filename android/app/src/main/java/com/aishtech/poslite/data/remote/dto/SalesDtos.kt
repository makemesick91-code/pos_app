package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * POST /api/v1/sales request. The client sends only what it is allowed to
 * influence — line items, payment method/amount, and notes. tenant_id,
 * cashier_id, invoice_number and every money total are set by the backend and
 * are deliberately absent from these DTOs (Sprint 4 runtime rule).
 */
data class CreateSaleRequestDto(
    @SerializedName("items") val items: List<CreateSaleItemRequestDto>,
    @SerializedName("payment") val payment: CashPaymentRequestDto,
    @SerializedName("notes") val notes: String? = null,
)

data class CreateSaleItemRequestDto(
    @SerializedName("product_id") val productId: Long,
    @SerializedName("qty") val qty: Int,
    @SerializedName("discount") val discount: String = "0.00",
)

/** Inline CASH payment. Sprint 4 supports CASH only; never a gateway method. */
data class CashPaymentRequestDto(
    @SerializedName("method") val method: String = "CASH",
    @SerializedName("paid_amount") val paidAmount: String,
)

/** POST/GET /api/v1/sales response envelope. */
data class SaleResponse(
    @SerializedName("data") val data: SaleDto,
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class SaleDto(
    @SerializedName("id") val id: Long,
    @SerializedName("store_id") val storeId: Long?,
    @SerializedName("invoice_number") val invoiceNumber: String?,
    @SerializedName("sale_date") val saleDate: String?,
    @SerializedName("subtotal") val subtotal: String?,
    @SerializedName("discount_total") val discountTotal: String?,
    @SerializedName("tax_total") val taxTotal: String?,
    @SerializedName("grand_total") val grandTotal: String?,
    @SerializedName("paid_total") val paidTotal: String?,
    @SerializedName("change_total") val changeTotal: String?,
    @SerializedName("payment_status") val paymentStatus: String?,
    @SerializedName("sync_status") val syncStatus: String?,
    @SerializedName("source") val source: String?,
    @SerializedName("items") val items: List<SaleItemDto> = emptyList(),
    @SerializedName("payments") val payments: List<PaymentDto> = emptyList(),
)

data class SaleItemDto(
    @SerializedName("product_id") val productId: Long,
    @SerializedName("product_name") val productName: String?,
    @SerializedName("qty") val qty: String?,
    @SerializedName("unit_price") val unitPrice: String?,
    @SerializedName("subtotal") val subtotal: String?,
)

data class PaymentDto(
    @SerializedName("method") val method: String?,
    @SerializedName("amount") val amount: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("provider") val provider: String?,
)
