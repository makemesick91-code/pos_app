package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/**
 * Meta block returned by the sync endpoints. `updatedSince` echoes the
 * incremental cursor the client sent.
 */
data class MetaDto(
    @SerializedName("tenant_id") val tenantId: Long?,
    @SerializedName("store_id") val storeId: Long?,
    @SerializedName("updated_since") val updatedSince: String?,
    @SerializedName("foundation") val foundation: String?,
)
