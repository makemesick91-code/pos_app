package com.aishtech.poslite.data.remote.dto

import com.google.gson.annotations.SerializedName

/** Body for POST /api/v1/android/device/activate (Sprint 34). The raw token is
 *  sent once for verification and is never persisted/logged by the client. */
data class ActivateDeviceRequestDto(
    @SerializedName("activation_token") val activationToken: String,
    @SerializedName("device_fingerprint") val deviceFingerprint: String,
    @SerializedName("device_uuid") val deviceUuid: String?,
    @SerializedName("device_label") val deviceLabel: String?,
    // UIX-8C-07 — optional support/triage metadata. installation_id is the
    // app-generated installation id (never a hardware id); the server stores only
    // its hash. The raw values are never logged by the client.
    @SerializedName("app_version") val appVersion: String? = null,
    @SerializedName("installation_id") val installationId: String? = null,
)

/** Response of device activation — a safe activation record (no token/fingerprint). */
data class DeviceActivationResponseDto(
    @SerializedName("data") val data: DeviceActivationDto?,
)

data class DeviceActivationDto(
    @SerializedName("id") val id: Long?,
    @SerializedName("status") val status: String?,
    @SerializedName("device_id") val deviceId: Long?,
    @SerializedName("device_label") val deviceLabel: String?,
    @SerializedName("activated_at") val activatedAt: String?,
    @SerializedName("last_seen_at") val lastSeenAt: String?,
)

/** Response of GET /api/v1/android/runtime/policy (Sprint 34). */
data class AndroidRuntimePolicyResponseDto(
    @SerializedName("data") val data: AndroidRuntimePolicyDto?,
)

data class AndroidRuntimePolicyDto(
    @SerializedName("offline") val offline: OfflinePolicyDto?,
    @SerializedName("sync") val sync: SyncPolicyDto?,
    @SerializedName("runtime") val runtime: RuntimePostureDto?,
    @SerializedName("stale_entitlement_behavior") val staleBehavior: String?,
)

data class OfflinePolicyDto(
    @SerializedName("mode_allowed") val modeAllowed: Boolean?,
    @SerializedName("queue_max_items") val queueMaxItems: Int?,
    @SerializedName("queue_max_age_hours") val queueMaxAgeHours: Int?,
    @SerializedName("require_client_uuid") val requireClientUuid: Boolean?,
)

data class SyncPolicyDto(
    @SerializedName("batch_idempotency_required") val batchIdempotencyRequired: Boolean?,
    @SerializedName("max_items_per_batch") val maxItemsPerBatch: Int?,
    @SerializedName("require_item_client_id") val requireItemClientId: Boolean?,
)

data class RuntimePostureDto(
    @SerializedName("status") val status: String?,
    @SerializedName("write_allowed") val writeAllowed: Boolean?,
    @SerializedName("read_only") val readOnly: Boolean?,
    @SerializedName("reason_code") val reasonCode: String?,
    @SerializedName("billing_state") val billingState: String?,
)

/** Body for POST /api/v1/android/sync/batch (Sprint 34). */
data class SyncBatchRequestDto(
    @SerializedName("client_batch_id") val clientBatchId: String,
    @SerializedName("idempotency_key") val idempotencyKey: String?,
    @SerializedName("items") val items: List<SyncBatchItemDto>,
    @SerializedName("register_id") val registerId: Long? = null,
)

data class SyncBatchItemDto(
    @SerializedName("client_item_id") val clientItemId: String,
    @SerializedName("item_type") val itemType: String,
    @SerializedName("action") val action: String,
    @SerializedName("payload") val payload: Map<String, Any?>? = null,
)

/** Response of a sync batch submit — safe per-item outcomes. */
data class SyncBatchResponseDto(
    @SerializedName("data") val data: SyncBatchResultDto?,
)

data class SyncBatchResultDto(
    @SerializedName("client_batch_id") val clientBatchId: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("accepted_count") val acceptedCount: Int?,
    @SerializedName("duplicate_count") val duplicateCount: Int?,
    @SerializedName("conflict_count") val conflictCount: Int?,
    @SerializedName("failed_count") val failedCount: Int?,
    @SerializedName("idempotent_replay") val idempotentReplay: Boolean?,
    @SerializedName("items") val items: List<SyncBatchItemResultDto> = emptyList(),
)

data class SyncBatchItemResultDto(
    @SerializedName("client_item_id") val clientItemId: String?,
    @SerializedName("item_type") val itemType: String?,
    @SerializedName("status") val status: String?,
    @SerializedName("conflict_code") val conflictCode: String?,
)
