<?php

namespace App\Services\SupportOperations;

use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingPaymentIntent;

/**
 * Sprint 35 — read-only payment intent / gateway event diagnostics (SUP-R009).
 *
 * Reads the Sprint 31 tenant_billing_payment_intents / tenant_billing_gateway_events
 * safely. NEVER creates/settles/replays a payment, never marks an invoice paid.
 * Gateway signatures/payloads are never returned — only hashes/flags/statuses.
 */
class SupportPaymentViewerService
{
    public function summary(int $tenantId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $intents = TenantBillingPaymentIntent::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $events = TenantBillingGatewayEvent::query()
            ->whereIn('invoice_id', $intents->pluck('invoice_id')->filter()->unique()->all())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $intentsByStatus = [];
        foreach ($intents as $intent) {
            $intentsByStatus[$intent->status] = ($intentsByStatus[$intent->status] ?? 0) + 1;
        }

        $eventsByStatus = [];
        foreach ($events as $event) {
            $eventsByStatus[$event->normalized_status] = ($eventsByStatus[$event->normalized_status] ?? 0) + 1;
        }

        return [
            'read_only' => true,
            'intent_count' => $intents->count(),
            'intents_by_status' => $intentsByStatus,
            'gateway_event_count' => $events->count(),
            'gateway_events_by_status' => $eventsByStatus,
            'latest_intents' => $intents->take(5)->map(fn (TenantBillingPaymentIntent $p) => [
                'provider' => $p->provider,
                'channel' => $p->channel,
                'status' => $p->status,
                'amount' => (int) $p->amount,
                'currency' => $p->currency,
                'expires_at' => optional($p->expires_at)->toIso8601String(),
                'paid_at' => optional($p->paid_at)->toIso8601String(),
                'failed_at' => optional($p->failed_at)->toIso8601String(),
            ])->all(),
            'latest_events' => $events->take(5)->map(fn (TenantBillingGatewayEvent $e) => [
                'provider' => $e->provider,
                'event_type' => $e->event_type,
                'status' => $e->status,
                'normalized_status' => $e->normalized_status,
                'signature_verified' => (bool) $e->signature_verified,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ])->all(),
        ];
    }
}
