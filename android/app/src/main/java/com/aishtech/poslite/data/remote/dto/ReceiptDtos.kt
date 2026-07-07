package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * GET /api/v1/sales/{sale}/receipt response. The backend is the sole authority
 * for receipt content and print eligibility (Sprint 6 runtime rule). The app
 * never recomputes totals or decides FINAL/printable itself — it only renders
 * and formats what the backend approves. No gateway secret is present here; the
 * backend hides `raw_response` and never emits payment credentials.
 */
data class ReceiptResponseDto(
    @SerializedName("data") val data: ReceiptDto,
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class ReceiptDto(
    @SerializedName("sale_id") val saleId: Long,
    @SerializedName("invoice_number") val invoiceNumber: String?,
    @SerializedName("receipt_status") val receiptStatus: String?,
    @SerializedName("printable") val printable: Boolean = false,
    @SerializedName("print_block_reason") val printBlockReason: String?,
    @SerializedName("store") val store: ReceiptStoreDto?,
    @SerializedName("cashier") val cashier: ReceiptCashierDto?,
    @SerializedName("sale_date") val saleDate: String?,
    @SerializedName("payment_status") val paymentStatus: String?,
    @SerializedName("items") val items: List<ReceiptItemDto> = emptyList(),
    @SerializedName("payments") val payments: List<ReceiptPaymentDto> = emptyList(),
    @SerializedName("totals") val totals: ReceiptTotalsDto?,
    @SerializedName("footer") val footer: String?,
)

data class ReceiptStoreDto(
    @SerializedName("name") val name: String?,
    @SerializedName("code") val code: String?,
    @SerializedName("address") val address: String?,
)

data class ReceiptCashierDto(
    @SerializedName("name") val name: String?,
)

data class ReceiptItemDto(
    @SerializedName("product_name") val productName: String?,
    @SerializedName("product_sku") val productSku: String?,
    @SerializedName("qty") val qty: String?,
    @SerializedName("unit") val unit: String?,
    @SerializedName("unit_price") val unitPrice: String?,
    @SerializedName("discount") val discount: String?,
    @SerializedName("subtotal") val subtotal: String?,
)

data class ReceiptPaymentDto(
    @SerializedName("method") val method: String?,
    @SerializedName("provider") val provider: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("amount") val amount: String?,
    @SerializedName("paid_at") val paidAt: String?,
)

data class ReceiptTotalsDto(
    @SerializedName("subtotal") val subtotal: String?,
    @SerializedName("discount_total") val discountTotal: String?,
    @SerializedName("tax_total") val taxTotal: String?,
    @SerializedName("grand_total") val grandTotal: String?,
    @SerializedName("paid_total") val paidTotal: String?,
    @SerializedName("change_total") val changeTotal: String?,
)
