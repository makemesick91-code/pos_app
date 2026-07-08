<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantBillingGatewayEvent;
use App\Services\PaymentGateway\PaymentGatewayException;
use App\Services\PaymentGateway\PaymentGatewayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 31 — the provider settlement webhook (PGW-R007/R015).
 *
 * This is the ONLY unauthenticated write path in the gateway surface: trust comes
 * ENTIRELY from the provider signature, verified inside PaymentGatewayWebhookService.
 * It is NOT a tenant/user mutation route — it accepts no tenant identity and can
 * only advance intent/event state for a matching provider reference. An unsigned/
 * invalid event is rejected (never processed), and the response is minimal and
 * leaks no internals. It is kept SEPARATE from the Sprint 5 POS QRIS webhook
 * (/webhooks/payments/{provider}).
 */
class PaymentGatewayWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayWebhookService $webhooks,
    ) {}

    public function store(Request $request, string $provider): JsonResponse
    {
        try {
            $event = $this->webhooks->ingest(
                providerKey: $provider,
                payload: (array) $request->json()->all() ?: $request->all(),
                headers: $request->headers->all(),
            );
        } catch (PaymentGatewayException $e) {
            // Unknown/disabled provider — minimal, no internals.
            return response()->json(['status' => 'rejected'], 404);
        }

        if ($event->status === TenantBillingGatewayEvent::STATUS_REJECTED && ! $event->signature_verified) {
            return response()->json(['status' => 'rejected'], 401);
        }

        return response()->json(['status' => 'accepted', 'event' => $event->status], 200);
    }
}
