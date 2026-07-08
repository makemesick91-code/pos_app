<?php

namespace App\Http\Controllers\Api\V1\Android;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Android\SyncBatchRequest;
use App\Models\RegisteredDevice;
use App\Models\TenantAndroidSyncBatch;
use App\Services\AndroidRuntime\AndroidSyncBatchData;
use App\Services\AndroidRuntime\AndroidSyncIngestionService;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 34 — the Android sync batch endpoint (ADR-R012..R016). Idempotent: a
 * replayed client_batch_id returns the stored result with no re-mutation. Every
 * write dimension is resolved by AndroidSyncIngestionService through the canonical
 * runtime gate; this controller never bypasses it. Output is redacted/safe.
 */
class AndroidSyncController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AndroidSyncIngestionService $ingestion,
        private readonly DeviceActivationService $activation,
    ) {}

    public function store(SyncBatchRequest $request): JsonResponse
    {
        $tenant = $this->context->tenant();
        $device = $this->resolveDevice($request);

        if ($device === null) {
            return response()->json(['message' => 'Device not registered', 'code' => 'DEVICE_NOT_REGISTERED'], Response::HTTP_FORBIDDEN);
        }

        $activation = $this->activation->resolveForDevice($device);
        $data = AndroidSyncBatchData::fromArray($request->validated());

        $batch = $this->ingestion->ingest($this->context, $activation, $data);

        $replay = (bool) ($batch->wasReplay ?? false);

        return response()->json([
            'data' => array_merge($batch->toSafeArray(), [
                'idempotent_replay' => $replay,
                'items' => $batch->items->map->toSafeArray()->all(),
            ]),
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ], $replay ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function show(string $clientBatchId): JsonResponse
    {
        $batch = TenantAndroidSyncBatch::query()
            ->forTenant((int) $this->context->tenantId())
            ->where('client_batch_id', $clientBatchId)
            ->with('items')
            ->first();

        if ($batch === null) {
            return response()->json(['message' => 'Sync batch not found', 'code' => 'SYNC_BATCH_NOT_FOUND'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => array_merge($batch->toSafeArray(), [
                'items' => $batch->items->map->toSafeArray()->all(),
            ]),
        ]);
    }

    private function resolveDevice(Request $request): ?RegisteredDevice
    {
        $deviceUuid = trim((string) $request->header('X-Device-UUID'));

        if ($deviceUuid === '') {
            return null;
        }

        return RegisteredDevice::query()
            ->forTenant((int) $this->context->tenantId())
            ->where('device_uuid', $deviceUuid)
            ->first();
    }
}
