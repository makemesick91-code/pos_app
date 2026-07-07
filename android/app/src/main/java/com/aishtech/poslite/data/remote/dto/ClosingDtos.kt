package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * Sprint 9 — daily closing DTOs. The closing snapshot and its totals are created
 * and owned by the backend; the app only requests a close (store + business
 * date) and displays the returned snapshot. A duplicate close is surfaced via
 * meta.duplicate_replay so the UI can show "already closed".
 */

/** POST /api/v1/closings/daily */
data class CreateDailyClosingRequestDto(
    @SerializedName("store_id") val storeId: Long?,
    @SerializedName("business_date") val businessDate: String,
    @SerializedName("notes") val notes: String? = null,
)

/** POST/GET /api/v1/closings/daily/{id} */
data class DailyClosingResponseDto(
    @SerializedName("data") val data: DailyClosingDto?,
    @SerializedName("meta") val meta: ClosingMetaDto? = null,
)

data class DailyClosingListResponseDto(
    @SerializedName("data") val data: List<DailyClosingDto> = emptyList(),
    @SerializedName("meta") val meta: ClosingMetaDto? = null,
)

data class ClosingMetaDto(
    @SerializedName("tenant_id") val tenantId: Long? = null,
    @SerializedName("foundation") val foundation: String? = null,
    @SerializedName("duplicate_replay") val duplicateReplay: Boolean = false,
)

data class DailyClosingDto(
    @SerializedName("id") val id: Long,
    @SerializedName("tenant_id") val tenantId: Long?,
    @SerializedName("store_id") val storeId: Long?,
    @SerializedName("business_date") val businessDate: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("sales_count") val salesCount: Int = 0,
    @SerializedName("cancelled_sales_count") val cancelledSalesCount: Int = 0,
    @SerializedName("cash_total") val cashTotal: String?,
    @SerializedName("qris_total") val qrisTotal: String?,
    @SerializedName("gross_total") val grossTotal: String?,
    @SerializedName("grand_total") val grandTotal: String?,
    @SerializedName("paid_total") val paidTotal: String?,
    @SerializedName("change_total") val changeTotal: String?,
    @SerializedName("inventory_sale_out_qty") val inventorySaleOutQty: String?,
    @SerializedName("closed_at") val closedAt: String?,
)
