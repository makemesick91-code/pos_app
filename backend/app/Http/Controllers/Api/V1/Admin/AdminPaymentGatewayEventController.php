<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantBillingGatewayEventResource;
use App\Models\TenantBillingGatewayEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 31 — platform-admin READ-ONLY gateway event visibility (PGW-R014/R016).
 * There is deliberately NO admin route that re-settles or mutates a stored event;
 * settlement happens only through the verified webhook. Output is redacted.
 */
class AdminPaymentGatewayEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = TenantBillingGatewayEvent::query()
            ->when($request->query('provider'), fn ($q, $v) => $q->where('provider', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('intent'), fn ($q, $v) => $q->where('payment_intent_id', (int) $v))
            ->orderByDesc('id')
            ->paginate(50);

        return TenantBillingGatewayEventResource::collection($events);
    }

    public function show(TenantBillingGatewayEvent $event): TenantBillingGatewayEventResource
    {
        return new TenantBillingGatewayEventResource($event);
    }
}
