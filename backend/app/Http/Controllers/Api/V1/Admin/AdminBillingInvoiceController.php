<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\GenerateTenantInvoiceRequest;
use App\Http\Resources\Api\V1\Admin\TenantBillingInvoiceResource;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Services\Billing\BillingGovernanceException;
use App\Services\Billing\BillingPeriodService;
use App\Services\Billing\TenantInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 30 — platform-admin billing invoice visibility + generation (BIL-R003/
 * R007). Reads are cross-tenant governance data (platform.admin only). generate()
 * is idempotent per tenant + period (BIL-R002) and audit-logged. Amounts come
 * from plan pricing — never from the request body.
 */
class AdminBillingInvoiceController extends Controller
{
    public function __construct(
        private readonly TenantInvoiceService $invoices,
        private readonly BillingPeriodService $periods,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = TenantBillingInvoice::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('collection_state'), fn ($q, $v) => $q->where('collection_state', $v))
            ->when($request->query('period'), fn ($q, $v) => $q->where('period_key', $v))
            ->orderByDesc('id')
            ->paginate(50);

        return TenantBillingInvoiceResource::collection($invoices);
    }

    public function forTenant(Tenant $tenant): AnonymousResourceCollection
    {
        $invoices = TenantBillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->paginate(50);

        return TenantBillingInvoiceResource::collection($invoices);
    }

    public function generate(GenerateTenantInvoiceRequest $request, Tenant $tenant): JsonResponse
    {
        $periodKey = $request->validated('period')
            ?: $this->periods->resolveForDate()->key;

        try {
            $invoice = $this->invoices->generate(
                tenant: $tenant,
                periodKey: (string) $periodKey,
                source: (string) ($request->validated('source') ?: 'platform_admin'),
                actor: $request->user(),
                metadata: $request->validated('metadata'),
                request: $request,
            );
        } catch (BillingGovernanceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->governanceCode,
            ], 422);
        }

        return (new TenantBillingInvoiceResource($invoice))
            ->response()
            ->setStatusCode($invoice->wasRecentlyCreated ? 201 : 200);
    }
}
