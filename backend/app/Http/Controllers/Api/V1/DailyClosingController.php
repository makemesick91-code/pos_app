<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexDailyClosingRequest;
use App\Http\Requests\Api\V1\StoreDailyClosingRequest;
use App\Http\Resources\Api\V1\DailyClosingResource;
use App\Models\DailyClosing;
use App\Services\Reports\DailyClosingService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daily closing snapshots (Sprint 9). Closings are tenant-owned and store-scoped;
 * totals are computed by the backend at close time. A duplicate close for the
 * same (tenant, store, business_date) replays the existing closing rather than
 * creating a second one. Tenant B's closings are never visible here.
 */
class DailyClosingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DailyClosingService $service,
    ) {}

    public function index(IndexDailyClosingRequest $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->context->tenantId();

        $query = DailyClosing::query()
            ->forTenant($tenantId)
            ->orderByDesc('business_date')
            ->orderByDesc('id');

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('business_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('business_date', '<=', $request->date('date_to'));
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min($limit, 100));

        return DailyClosingResource::collection($query->limit($limit)->get())->additional([
            'meta' => [
                'tenant_id' => $tenantId,
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    public function store(StoreDailyClosingRequest $request): JsonResponse
    {
        $tenantId = (int) $this->context->tenantId();

        $closing = $this->service->close(
            tenantId: $tenantId,
            storeId: (int) $request->input('store_id'),
            businessDate: (string) $request->input('business_date'),
            closedBy: (int) $this->context->user()->id,
            notes: $request->input('notes'),
        );

        $status = $closing->duplicateReplay ? Response::HTTP_OK : Response::HTTP_CREATED;

        return DailyClosingResource::make($closing)
            ->additional([
                'meta' => [
                    'tenant_id' => $tenantId,
                    'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                    'duplicate_replay' => $closing->duplicateReplay,
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }

    public function show(DailyClosing $dailyClosing): DailyClosingResource
    {
        $this->authorizeTenant($dailyClosing);

        return DailyClosingResource::make($dailyClosing)->additional([
            'meta' => [
                'tenant_id' => (int) $this->context->tenantId(),
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ]);
    }

    private function authorizeTenant(DailyClosing $dailyClosing): void
    {
        abort_if(
            (int) $dailyClosing->tenant_id !== (int) $this->context->tenantId(),
            Response::HTTP_NOT_FOUND,
        );
    }
}
