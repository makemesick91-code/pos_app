<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CreatePaymentIntentRequest;
use App\Http\Resources\Api\V1\Admin\TenantBillingPaymentIntentResource;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 31 — platform-admin payment intent visibility + creation (PGW-R014).
 * Reads are cross-tenant governance data. create() is idempotent per invoice +
 * provider + channel (PGW-R003), refuses a paid invoice (PGW-R004), and the
 * amount always equals the invoice outstanding amount — never the request body.
 */
class AdminPaymentGatewayIntentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayIntentService $intents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $intents = TenantBillingPaymentIntent::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('provider'), fn ($q, $v) => $q->where('provider', $v))
            ->when($request->query('tenant'), fn ($q, $v) => $q->where('tenant_id', (int) $v))
            ->orderByDesc('id')
            ->paginate(50);

        return TenantBillingPaymentIntentResource::collection($intents);
    }

    public function show(TenantBillingPaymentIntent $intent): TenantBillingPaymentIntentResource
    {
        return new TenantBillingPaymentIntentResource($intent);
    }

    public function store(CreatePaymentIntentRequest $request, TenantBillingInvoice $invoice): JsonResponse
    {
        try {
            $intent = $this->intents->create(
                invoice: $invoice,
                provider: $request->validated('provider'),
                channel: $request->validated('channel'),
                actor: $request->user(),
                source: 'platform_admin',
                metadata: $request->validated('metadata'),
                idempotencyKey: $request->validated('idempotency_key') ?: $request->header('Idempotency-Key'),
                request: $request,
            );
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->governanceCode], 422);
        }

        return (new TenantBillingPaymentIntentResource($intent))
            ->response()
            ->setStatusCode($intent->wasRecentlyCreated ? 201 : 200);
    }
}
