<?php

namespace App\Services\Performance;

use App\Models\PerformanceQueryReview;

class IndexReviewService
{
    public function review(bool $execute = false): array
    {
        $areas = [
            ['tenant', 'tenants', 'status/code lookup'],
            ['product', 'products', 'tenant_id + updated_at sync lookup'],
            ['pos_sale', 'sales', 'tenant_id + store_id + client_reference idempotency lookup'],
            ['android_sync', 'tenant_android_sync_batches', 'tenant_id + client_batch_id replay lookup'],
            ['import', 'tenant_data_import_runs', 'tenant_id + idempotency_key retry lookup'],
            ['export_report', 'tenant_usage_events', 'tenant_id + event_key metering lookup'],
            ['billing', 'tenant_billing_invoices', 'tenant_id + status aging lookup'],
            ['payment', 'tenant_billing_gateway_events', 'provider_reference replay lookup'],
            ['entitlement', 'tenant_entitlement_decisions', 'tenant_id + decision lookup'],
            ['onboarding', 'tenant_provisioning_runs', 'tenant_id + status lookup'],
            ['support', 'tenant_support_incidents', 'tenant_id + status lookup'],
            ['observability', 'observability_health_snapshots', 'area + status lookup'],
            ['queue', 'jobs', 'queue + available_at lookup'],
        ];
        $out = [];
        foreach ($areas as [$area, $table, $pattern]) {
            $payload = [
                'review_key' => 's38-'.$area,
                'area' => $area,
                'status' => 'observed',
                'table_name' => $table,
                'index_name' => null,
                'query_pattern_safe' => $pattern,
                'before_metric_json' => ['evidence' => 'schema_review'],
                'decision_reason' => 'No blind index added; existing query pattern recorded for pilot evidence.',
                'metadata_json' => ['execute' => $execute],
            ];
            if ($execute) {
                PerformanceQueryReview::query()->updateOrCreate(['review_key' => $payload['review_key']], $payload);
            }
            $out[] = $payload;
        }
        return $out;
    }
}
