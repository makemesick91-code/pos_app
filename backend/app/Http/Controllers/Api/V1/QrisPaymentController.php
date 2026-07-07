<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQrisPaymentRequest;
use App\Http\Resources\Api\V1\QrisPaymentResource;
use App\Models\Sale;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\QrisPaymentService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Creates a QRIS payment for a tenant-owned sale. Cross-tenant sales 404 (a
 * tenant can never learn another tenant's sale exists). Disabled/unknown
 * providers become a 422 rather than a 500.
 */
class QrisPaymentController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QrisPaymentService $qris,
    ) {}

    public function store(StoreQrisPaymentRequest $request, Sale $sale): JsonResponse
    {
        abort_unless(
            (int) $sale->tenant_id === (int) $this->context->tenantId(),
            404
        );

        try {
            $payment = $this->qris->createForSale($sale, $request->input('provider'));
        } catch (PaymentGatewayException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['provider' => [$e->getMessage()]],
            ], 422);
        }

        return QrisPaymentResource::make($payment->loadMissing('sale'))
            ->response()
            ->setStatusCode(201);
    }
}
