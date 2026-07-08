<?php

namespace App\Services\PaymentGateway;

use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingPaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 31 — read-only, redacted aggregate visibility over providers, intents,
 * events, and settlements (PGW-R016). Returns counts/amounts only — never a
 * secret, signature, raw payload, or per-customer PII.
 */
class PaymentGatewaySummaryService
{
    /**
     * Safe provider/channel posture — no credential VALUES, only names + counts.
     *
     * @return array<string, mixed>
     */
    public function providerSummary(): array
    {
        $providers = (array) config('payment_gateway_governance.providers', []);
        $out = [];

        foreach ($providers as $key => $def) {
            $out[] = [
                'provider' => $key,
                'label' => (string) ($def['label'] ?? $key),
                'enabled' => (bool) ($def['enabled'] ?? false),
                'live' => (bool) ($def['live'] ?? false),
                'channels' => array_values((array) ($def['channels'] ?? [])),
                'credential_env_count' => count((array) ($def['credentials_env'] ?? [])),
            ];
        }

        return [
            'default_provider' => (string) config('payment_gateway_governance.default_provider', 'mock'),
            'live_gateway_enabled' => (bool) config('payment_gateway_governance.live_gateway_enabled', false),
            'providers' => $out,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function intentSummary(?int $tenantId = null): array
    {
        if (! Schema::hasTable('tenant_billing_payment_intents')) {
            return ['total' => 0, 'by_status' => []];
        }

        $query = TenantBillingPaymentIntent::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'total' => (clone $query)->count(),
            'by_status' => $this->countByColumn((clone $query), 'status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventSummary(?int $tenantId = null): array
    {
        if (! Schema::hasTable('tenant_billing_gateway_events')) {
            return ['total' => 0, 'by_status' => [], 'by_normalized_status' => []];
        }

        $query = TenantBillingGatewayEvent::query()
            ->when($tenantId, fn ($q) => $q->whereHas('invoice', fn ($i) => $i->where('tenant_id', $tenantId)));

        return [
            'total' => (clone $query)->count(),
            'by_status' => $this->countByColumn((clone $query), 'status'),
            'by_normalized_status' => $this->countByColumn((clone $query), 'normalized_status'),
            'rejected' => (clone $query)->where('status', TenantBillingGatewayEvent::STATUS_REJECTED)->count(),
            'replayed' => (clone $query)->where('status', TenantBillingGatewayEvent::STATUS_REPLAYED)->count(),
        ];
    }

    /**
     * Settlement outcomes: how many intents actually reached `paid`, and the total
     * settled amount. Aggregate only.
     *
     * @return array<string, mixed>
     */
    public function settlementSummary(?int $tenantId = null): array
    {
        if (! Schema::hasTable('tenant_billing_payment_intents')) {
            return ['settled_intents' => 0, 'settled_amount' => 0];
        }

        $paid = TenantBillingPaymentIntent::query()
            ->where('status', TenantBillingPaymentIntent::STATUS_PAID)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'settled_intents' => (clone $paid)->count(),
            'settled_amount' => (int) (clone $paid)->sum('amount'),
            'open_intents' => TenantBillingPaymentIntent::query()
                ->whereIn('status', TenantBillingPaymentIntent::OPEN_STATUSES)
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countByColumn($query, string $column): array
    {
        return $query->select($column, DB::raw('COUNT(*) as c'))
            ->groupBy($column)
            ->pluck('c', $column)
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }
}
