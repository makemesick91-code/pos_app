<?php

namespace App\Services\Observability;

use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — billing/payment webhook anomaly detection (OBS-R014/R015).
 *
 * READ-ONLY. Sources from the Sprint 30 invoice/collection state and the Sprint 31
 * gateway events / payment intents / payments. Detects overdue-past-grace
 * invoices, repeated failed/cancelled payments, invalid-signature (rejected)
 * webhook spikes and stuck-pending payment intents. It NEVER marks an invoice
 * paid, mutates a settlement, or touches any billing state.
 */
class BillingPaymentAnomalyService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(?int $tenantId = null): array
    {
        $t = (array) config('observability_governance.thresholds', []);
        $now = Carbon::now();
        $since = $now->copy()->subHours((int) config('observability_governance.anomaly.default_lookback_hours', 168));
        $graceDays = (int) ($t['billing_grace_days'] ?? 7);

        $anomalies = [];

        // Overdue-past-grace invoices per tenant (Sprint 30).
        $invoiceQuery = TenantBillingInvoice::query()
            ->whereIn('collection_state', [TenantBillingInvoice::COLLECTION_OVERDUE, TenantBillingInvoice::COLLECTION_FAILED])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));
        $overdueByTenant = [];
        foreach ($invoiceQuery->get(['id', 'tenant_id', 'due_at']) as $invoice) {
            $dueAt = $invoice->due_at;
            if ($dueAt !== null && $dueAt->copy()->addDays($graceDays)->isPast($now)) {
                $overdueByTenant[$invoice->tenant_id] = ($overdueByTenant[$invoice->tenant_id] ?? 0) + 1;
            }
        }
        foreach ($overdueByTenant as $tid => $count) {
            $anomalies[] = $this->descriptor((int) $tid, 'billing', 'billing.overdue_past_grace', 'high',
                'invoice_overdue_past_grace', $count.' invoice(s) overdue past grace.', ['overdue_count' => (int) $count]);
        }

        // Repeated failed/cancelled payments per tenant (Sprint 31).
        $failedPayByTenant = TenantBillingPayment::query()
            ->whereIn('status', [TenantBillingPayment::STATUS_FAILED, TenantBillingPayment::STATUS_CANCELLED])
            ->where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');
        foreach ($failedPayByTenant as $tid => $count) {
            if ((int) $count >= (int) ($t['payment_failed_event_watch'] ?? 3)) {
                $anomalies[] = $this->descriptor((int) $tid, 'payment', 'payment.repeated_failed', 'medium',
                    'repeated_failed_payments', (int) $count.' failed/cancelled payment(s) in window.', ['failed_payment_count' => (int) $count]);
            }
        }

        // Invalid-signature (rejected) webhook spikes. Gateway events carry no
        // tenant_id (a rejected signature is often not tenant-attributable), so
        // this is an APP-LEVEL anomaly (tenant_id null). Only run on a full scan.
        if ($tenantId === null) {
            $rejectedCount = (int) TenantBillingGatewayEvent::query()
                ->where('status', TenantBillingGatewayEvent::STATUS_REJECTED)
                ->where('created_at', '>=', $since)
                ->count();
            if ($rejectedCount >= (int) ($t['webhook_invalid_signature_watch'] ?? 3)) {
                $anomalies[] = $this->descriptor(null, 'payment', 'payment.webhook_rejected_spike', 'high',
                    'webhook_rejected_spike', $rejectedCount.' rejected gateway webhook event(s) in window.', ['rejected_event_count' => $rejectedCount]);
            }
        }

        // Stuck-pending payment intents per tenant (Sprint 31).
        $stuckBefore = $now->copy()->subMinutes((int) ($t['payment_intent_stuck_minutes'] ?? 120));
        $stuckByTenant = TenantBillingPaymentIntent::query()
            ->whereIn('status', TenantBillingPaymentIntent::OPEN_STATUSES)
            ->where('created_at', '<=', $stuckBefore)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('tenant_id, count(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');
        foreach ($stuckByTenant as $tid => $count) {
            $anomalies[] = $this->descriptor((int) $tid, 'payment', 'payment.intent_stuck_pending', 'medium',
                'payment_intent_stuck_pending', (int) $count.' payment intent(s) stuck pending.', ['stuck_intent_count' => (int) $count]);
        }

        return $anomalies;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function descriptor(?int $tenantId, string $category, string $key, string $severity, string $reason, string $summary, array $metadata): array
    {
        return [
            'tenant_id' => $tenantId,
            'anomaly_key' => $key,
            'category' => $category,
            'severity' => $severity,
            'reason_code' => $reason,
            'summary_safe' => $summary,
            'metadata' => $metadata,
        ];
    }
}
