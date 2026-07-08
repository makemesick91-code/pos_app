<?php

namespace App\Services\AndroidRuntime;

use App\Models\Sale;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantDeviceActivation;
use App\Services\SaleService;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Sprint 34 — accepts an Android sync batch and applies it deterministically and
 * idempotently (ADR-R012..R016).
 *
 * Idempotency is defended at two levels:
 *  - batch level: a replayed (tenant, client_batch_id) / idempotency_key resumes the
 *    stored batch and never re-mutates (ADR-R014);
 *  - item level: a client_item_id already ACCEPTED for the tenant is recorded as a
 *    duplicate with no second mutation (ADR-R013). Sale items additionally lean on
 *    the Sprint 7 SaleService client_reference idempotency, so the POS domain
 *    service is never bypassed (config sync_bypasses_pos_domain_service_allowed=false).
 *
 * A revoked/suspended/unpaid/trial-expired/register-mismatch batch is REJECTED with
 * each item recorded as a deterministic conflict (ADR-R016/R026/R027). A payment
 * item is never applied as settlement — Android may not invent settlement state
 * (ADR-R023/R024); it is recorded skipped for the trusted Sprint 30/31 services.
 */
class AndroidSyncIngestionService
{
    public function __construct(
        private readonly AndroidRuntimeAccessService $access,
        private readonly AndroidSyncConflictService $conflicts,
        private readonly AndroidSyncRedactor $redactor,
        private readonly SaleService $sales,
    ) {}

    public function ingest(TenantContext $context, TenantDeviceActivation $activation, AndroidSyncBatchData $data): TenantAndroidSyncBatch
    {
        $tenant = $context->tenant();
        $cashier = $context->user();

        // Batch idempotency (ADR-R014).
        $existing = TenantAndroidSyncBatch::query()
            ->forTenant($tenant->id)
            ->where(function ($q) use ($data) {
                $q->where('client_batch_id', $data->clientBatchId)
                    ->orWhere('idempotency_key', $data->idempotencyKey);
            })
            ->first();

        if ($existing instanceof TenantAndroidSyncBatch) {
            $existing->wasReplay = true;

            return $existing->load('items');
        }

        // Runtime gate (ADR-R007/R008/R009/R026): a denied tenant/device rejects the
        // whole batch and records each item as a deterministic conflict.
        $decision = $this->access->authorizeSync($tenant, $activation, $cashier);
        if ($decision->denied()) {
            return $this->rejectBatch($context, $activation, $data, $decision);
        }

        $maxItems = (int) config('android_runtime_governance.sync.max_items_per_batch', 200);
        if (count($data->items) > $maxItems) {
            $decision = new AndroidRuntimeDecision(
                allowed: false,
                status: AndroidRuntimeDecision::STATUS_BLOCKED,
                reasonCode: 'BATCH_TOO_LARGE',
                message: 'Sync batch exceeds the maximum item count.',
                conflictCode: AndroidSyncConflictService::CODE_INVALID_PAYLOAD,
                httpStatus: 422,
            );

            return $this->rejectBatch($context, $activation, $data, $decision);
        }

        $batch = TenantAndroidSyncBatch::query()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $context->storeId(),
            'register_id' => $data->registerId,
            'device_activation_id' => $activation->id,
            'cashier_user_id' => $cashier?->id,
            'client_batch_id' => $data->clientBatchId,
            'idempotency_key' => $data->idempotencyKey,
            'status' => TenantAndroidSyncBatch::STATUS_PROCESSING,
            'item_count' => count($data->items),
            'started_at' => now(),
        ]);

        $accepted = $rejected = $duplicate = $conflict = $failed = 0;

        foreach ($data->items as $raw) {
            $result = $this->processItem($context, $batch, (array) $raw);

            match ($result) {
                TenantAndroidSyncItem::STATUS_ACCEPTED => $accepted++,
                TenantAndroidSyncItem::STATUS_DUPLICATE => $duplicate++,
                TenantAndroidSyncItem::STATUS_CONFLICT => $conflict++,
                TenantAndroidSyncItem::STATUS_FAILED => $failed++,
                TenantAndroidSyncItem::STATUS_REJECTED => $rejected++,
                default => null,
            };
        }

        $status = match (true) {
            $failed > 0 && $accepted === 0 && $duplicate === 0 => TenantAndroidSyncBatch::STATUS_FAILED,
            ($failed > 0 || $conflict > 0 || $rejected > 0) => TenantAndroidSyncBatch::STATUS_PARTIAL_FAILED,
            default => TenantAndroidSyncBatch::STATUS_COMPLETED,
        };

        $batch->forceFill([
            'status' => $status,
            'accepted_count' => $accepted,
            'rejected_count' => $rejected,
            'duplicate_count' => $duplicate,
            'conflict_count' => $conflict,
            'failed_count' => $failed,
            'completed_at' => now(),
        ])->save();

        return $batch->load('items');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function processItem(TenantContext $context, TenantAndroidSyncBatch $batch, array $raw): string
    {
        $tenant = $context->tenant();
        $clientItemId = trim((string) ($raw['client_item_id'] ?? ''));
        $type = (string) ($raw['item_type'] ?? TenantAndroidSyncItem::TYPE_SALE);
        $action = (string) ($raw['action'] ?? 'create');
        $payload = (array) ($raw['payload'] ?? []);
        $payloadHash = hash('sha256', json_encode($payload) ?: $clientItemId);

        // ADR-R012 — an item without a client UUID is rejected, never mutated.
        if ($clientItemId === '') {
            return $this->recordItem($batch, '(missing)', $type, $action, TenantAndroidSyncItem::STATUS_REJECTED, $payloadHash, conflictCode: AndroidSyncConflictService::CODE_INVALID_PAYLOAD, failure: 'Missing client_item_id.');
        }

        if (! in_array($type, (array) config('android_runtime_governance.sync.item_types', []), true)) {
            return $this->recordItem($batch, $clientItemId, $type, $action, TenantAndroidSyncItem::STATUS_REJECTED, $payloadHash, conflictCode: AndroidSyncConflictService::CODE_INVALID_PAYLOAD, failure: 'Unknown item type.');
        }

        // Item-level idempotency (ADR-R013): a client_item_id already accepted for
        // this tenant is a duplicate — never mutate twice.
        $prior = TenantAndroidSyncItem::query()
            ->forTenant($tenant->id)
            ->where('client_item_id', $clientItemId)
            ->where('status', TenantAndroidSyncItem::STATUS_ACCEPTED)
            ->first();

        if ($prior instanceof TenantAndroidSyncItem) {
            return $this->recordItem(
                $batch,
                $clientItemId,
                $type,
                $action,
                TenantAndroidSyncItem::STATUS_DUPLICATE,
                $payloadHash,
                subjectType: $prior->server_subject_type,
                subjectId: $prior->server_subject_id,
                conflictCode: AndroidSyncConflictService::CODE_DUPLICATE,
            );
        }

        // Payment items never carry settlement (ADR-R023/R024).
        if ($type === TenantAndroidSyncItem::TYPE_PAYMENT) {
            return $this->recordItem($batch, $clientItemId, $type, $action, TenantAndroidSyncItem::STATUS_SKIPPED, $payloadHash, failure: 'PAYMENT_SETTLEMENT_SERVER_ONLY');
        }

        // Sale create → delegate to the Sprint 7 idempotent POS domain service.
        if ($type === TenantAndroidSyncItem::TYPE_SALE && $action === 'create') {
            return $this->processSale($context, $batch, $clientItemId, $payload, $payloadHash);
        }

        // Orders / snapshots / other → recorded as an accepted envelope (no financial
        // mutation invented here). This is the safe sync foundation.
        return $this->recordItem($batch, $clientItemId, $type, $action, TenantAndroidSyncItem::STATUS_ACCEPTED, $payloadHash);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processSale(TenantContext $context, TenantAndroidSyncBatch $batch, string $clientItemId, array $payload, string $payloadHash): string
    {
        // Force the client item id to be the idempotency reference so a retry can
        // never create a duplicate sale (ADR-R013/R015).
        $payload['client_reference'] = $clientItemId;
        $payload['source'] = Sale::SOURCE_ANDROID_OFFLINE;

        try {
            $sale = $this->sales->createCashSale($context, $payload);
        } catch (ValidationException $e) {
            return $this->recordItem(
                $batch,
                $clientItemId,
                TenantAndroidSyncItem::TYPE_SALE,
                'create',
                TenantAndroidSyncItem::STATUS_FAILED,
                $payloadHash,
                failure: 'INVALID_SALE_PAYLOAD',
            );
        }

        $status = ($sale->idempotentReplay ?? false)
            ? TenantAndroidSyncItem::STATUS_DUPLICATE
            : TenantAndroidSyncItem::STATUS_ACCEPTED;

        return $this->recordItem(
            $batch,
            $clientItemId,
            TenantAndroidSyncItem::TYPE_SALE,
            'create',
            $status,
            $payloadHash,
            subjectType: Sale::class,
            subjectId: $sale->id,
            conflictCode: $status === TenantAndroidSyncItem::STATUS_DUPLICATE ? AndroidSyncConflictService::CODE_DUPLICATE : null,
        );
    }

    private function rejectBatch(TenantContext $context, TenantDeviceActivation $activation, AndroidSyncBatchData $data, AndroidRuntimeDecision $decision): TenantAndroidSyncBatch
    {
        $tenant = $context->tenant();
        $conflictCode = $this->conflicts->normalize($decision->conflictCode ?? AndroidSyncConflictService::CODE_ENTITLEMENT_DENIED);

        $batch = TenantAndroidSyncBatch::query()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $context->storeId(),
            'register_id' => $data->registerId,
            'device_activation_id' => $activation->id,
            'cashier_user_id' => $context->user()?->id,
            'client_batch_id' => $data->clientBatchId,
            'idempotency_key' => $data->idempotencyKey,
            'status' => TenantAndroidSyncBatch::STATUS_REJECTED,
            'item_count' => count($data->items),
            'conflict_count' => count($data->items),
            'started_at' => now(),
            'completed_at' => now(),
            'failure_reason' => $decision->reasonCode,
            'metadata_json' => $this->redactor->redact(['reason_code' => $decision->reasonCode, 'conflict_code' => $conflictCode]),
        ]);

        foreach ($data->items as $raw) {
            $clientItemId = trim((string) (((array) $raw)['client_item_id'] ?? '(missing)'));
            $type = (string) (((array) $raw)['item_type'] ?? TenantAndroidSyncItem::TYPE_SALE);
            $this->recordItem($batch, $clientItemId, $type, (string) (((array) $raw)['action'] ?? 'create'), TenantAndroidSyncItem::STATUS_CONFLICT, null, conflictCode: $conflictCode, failure: $decision->reasonCode);
        }

        $batch->wasReplay = false;

        return $batch->load('items');
    }

    private function recordItem(
        TenantAndroidSyncBatch $batch,
        string $clientItemId,
        string $type,
        string $action,
        string $status,
        ?string $payloadHash,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $conflictCode = null,
        ?string $failure = null,
    ): string {
        TenantAndroidSyncItem::query()->create([
            'sync_batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'client_item_id' => $clientItemId,
            'item_type' => $type,
            'action' => $action,
            'status' => $status,
            'server_subject_type' => $subjectType,
            'server_subject_id' => $subjectId,
            'conflict_code' => $conflictCode,
            'failure_reason' => $failure,
            'payload_hash' => $payloadHash,
        ]);

        return $status;
    }
}
