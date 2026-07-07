<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResetTenantDemoDataRequest;
use App\Http\Requests\Api\V1\Admin\SeedTenantDemoDataRequest;
use App\Http\Resources\Api\V1\Admin\TenantDemoDataResource;
use App\Models\AdminAuditLog;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantOnboardingRun;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Onboarding\DemoDataResetService;
use App\Services\Onboarding\DemoDataSeederService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 12 — demo data seed/reset for an existing tenant. Platform admin only.
 * Seeding is tenant/store isolated and idempotent; reset is guarded by
 * confirm_demo_reset and only removes data recorded in a backend-owned demo
 * manifest. Every action is audit-logged.
 */
class TenantDemoDataController extends Controller
{
    public function __construct(
        private readonly DemoDataSeederService $seeder,
        private readonly DemoDataResetService $resetService,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function store(SeedTenantDemoDataRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validated();
        $store = $this->resolveStore($tenant, isset($data['store_id']) ? (int) $data['store_id'] : null);

        $result = $this->seeder->seed($tenant, $store, [
            'seed_products' => $data['seed_products'] ?? true,
            'seed_opening_inventory' => $data['seed_opening_inventory'] ?? true,
            'seed_demo_sales' => $data['seed_demo_sales'] ?? false,
        ]);

        $this->recordManifest($request->user()->id, $tenant, $result['manifest']);

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEMO_DATA_SEEDED,
            targetType: AdminAuditLog::TARGET_TENANT,
            targetId: $tenant->id,
            tenantId: $tenant->id,
            metadata: [
                'store_id' => $store->id,
                'products_seeded' => count($result['manifest']['product_ids']),
                'categories_seeded' => count($result['manifest']['category_ids']),
                'movements_seeded' => count($result['manifest']['movement_ids']),
            ],
            request: $request,
        );

        return TenantDemoDataResource::make([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'checklist' => $result['checklist'],
            'counts' => [
                'categories' => count($result['manifest']['category_ids']),
                'products' => count($result['manifest']['product_ids']),
                'prices' => count($result['manifest']['price_ids']),
                'opening_movements' => count($result['manifest']['movement_ids']),
            ],
            'notes' => $result['notes'],
        ])->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function reset(ResetTenantDemoDataRequest $request, Tenant $tenant): JsonResponse
    {
        $dryRun = (bool) ($request->validated()['dry_run'] ?? false);

        $summary = $this->resetService->reset(
            actor: $request->user(),
            tenant: $tenant,
            dryRun: $dryRun,
            request: $request,
        );

        return TenantDemoDataResource::make([
            'tenant_id' => $tenant->id,
            'dry_run' => $summary['dry_run'],
            'deleted' => $summary['deleted'],
            'manifest_runs' => $summary['manifest_runs'],
        ])->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ])->response()->setStatusCode(Response::HTTP_OK);
    }

    private function resolveStore(Tenant $tenant, ?int $storeId): Store
    {
        if ($storeId !== null) {
            $store = Store::query()->where('tenant_id', $tenant->id)->find($storeId);

            if ($store === null) {
                throw ValidationException::withMessages([
                    'store_id' => 'The selected store does not belong to this tenant.',
                ]);
            }

            return $store;
        }

        $store = $tenant->stores()->orderBy('id')->first();

        if ($store === null) {
            throw ValidationException::withMessages([
                'store_id' => 'This tenant has no store to seed demo data into.',
            ]);
        }

        return $store;
    }

    /**
     * Persist the demo manifest on a stable per-tenant demo-seed run so the
     * guarded reset can later delete exactly what was seeded. Merges with any
     * previously recorded manifest (idempotent seeding keeps ids stable).
     *
     * @param  array<string, array<int, int>>  $manifest
     */
    private function recordManifest(int $actorId, Tenant $tenant, array $manifest): void
    {
        $reference = 'demo-seed:'.$tenant->id;

        $run = TenantOnboardingRun::query()->firstOrNew(['onboarding_reference' => $reference]);

        $existing = $run->exists ? $run->demoManifest() : [];
        $merged = [];
        foreach (['category_ids', 'product_ids', 'price_ids', 'movement_ids', 'sale_ids', 'payment_ids'] as $key) {
            $merged[$key] = array_values(array_unique(array_merge(
                array_map('intval', $existing[$key] ?? []),
                array_map('intval', $manifest[$key] ?? []),
            )));
        }

        $run->fill([
            'requested_by' => $run->exists ? $run->requested_by : $actorId,
            'tenant_id' => $tenant->id,
            'default_store_id' => $run->default_store_id,
            'status' => TenantOnboardingRun::STATUS_COMPLETED,
            'tenant_name' => $tenant->name,
            'demo_data_enabled' => true,
            'demo_data_seeded_at' => Carbon::now(),
            'metadata' => array_merge($run->metadata ?? [], ['demo_manifest' => $merged]),
        ]);
        $run->save();
    }
}
