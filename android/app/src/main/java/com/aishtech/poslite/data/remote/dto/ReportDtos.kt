package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * Sprint 9 — report DTOs. All report totals are authoritative on the backend;
 * the app only displays them and never recomputes a total. Money fields are
 * decimal strings on the wire (Laravel decimal cast, e.g. "100000.00") and are
 * shown as-is or parsed defensively for formatting.
 */

/** GET /api/v1/reports/daily-sales */
data class DailySalesReportResponseDto(
    @SerializedName("data") val data: DailySalesReportDto?,
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class DailySalesReportDto(
    @SerializedName("business_date") val businessDate: String?,
    @SerializedName("store_id") val storeId: Long?,
    @SerializedName("sales_count") val salesCount: Int = 0,
    @SerializedName("cancelled_sales_count") val cancelledSalesCount: Int = 0,
    @SerializedName("gross_total") val grossTotal: String?,
    @SerializedName("discount_total") val discountTotal: String?,
    @SerializedName("tax_total") val taxTotal: String?,
    @SerializedName("grand_total") val grandTotal: String?,
    @SerializedName("paid_total") val paidTotal: String?,
    @SerializedName("change_total") val changeTotal: String?,
    @SerializedName("average_sale") val averageSale: String?,
    @SerializedName("cash_sales_count") val cashSalesCount: Int = 0,
    @SerializedName("qris_sales_count") val qrisSalesCount: Int = 0,
)

/** GET /api/v1/reports/payment-summary */
data class PaymentSummaryResponseDto(
    @SerializedName("data") val data: List<PaymentSummaryItemDto> = emptyList(),
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class PaymentSummaryItemDto(
    @SerializedName("method") val method: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("count") val count: Int = 0,
    @SerializedName("amount_total") val amountTotal: String?,
)

/** GET /api/v1/reports/inventory-movements-summary */
data class InventoryMovementSummaryResponseDto(
    @SerializedName("data") val data: List<InventoryMovementSummaryItemDto> = emptyList(),
    @SerializedName("meta") val meta: MetaDto? = null,
)

data class InventoryMovementSummaryItemDto(
    @SerializedName("movement_type") val movementType: String?,
    @SerializedName("movement_count") val movementCount: Int = 0,
    @SerializedName("qty_total") val qtyTotal: String?,
    @SerializedName("signed_qty_total") val signedQtyTotal: String?,
)
