<?php

namespace App\Services\SupportOperations;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantAndroidSyncItem;
use App\Models\TenantBillingGatewayEvent;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\TenantDeviceActivation;
use App\Models\TenantEntitlementDecision;
use App\Models\TenantProvisioningRun;
use App\Models\TenantSupportAction;
use App\Models\TenantSupportIncident;
use Illuminate\Support\Carbon;

/**
 * Sprint 35 — a deterministic, tenant-isolated diagnostic timeline (SUP-R003/R020).
 *
 * Merges events from the Sprint 30/31/32/33/34 ledgers plus support incidents/
 * actions and (safe) admin audit logs into one chronologically-sorted stream.
 * Every event carries only a safe code + short summary — never a raw payload,
 * token, signature or PII. Ordering is deterministic: by timestamp desc, then by
 * source then id, so the same tenant state always yields the same timeline.
 */
class SupportDiagnosticTimelineService
{
    public function __construct(private readonly SupportRedactor $redactor) {}

    /**
     * @param  array{category?: string|null, source?: string|null, since?: string|null, limit?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, array $filters = []): array
    {
        $since = isset($filters['since']) && $filters['since'] !== null
            ? Carbon::parse($filters['since'])
            : null;
        $sourceFilter = $filters['source'] ?? null;
        $categoryFilter = $filters['category'] ?? null;
        $limit = (int) ($filters['limit'] ?? config('support_operations_governance.timeline.default_limit', 100));
        $limit = max(1, min($limit, (int) config('support_operations_governance.timeline.max_limit', 500)));

        $events = array_merge(
            $this->onboardingEvents($tenant),
            $this->invoiceEvents($tenant),
            $this->paymentIntentEvents($tenant),
            $this->gatewayEvents($tenant),
            $this->entitlementEvents($tenant),
            $this->deviceEvents($tenant),
            $this->syncBatchEvents($tenant),
            $this->syncConflictEvents($tenant),
            $this->incidentEvents($tenant),
            $this->supportActionEvents($tenant),
            $this->adminAuditEvents($tenant),
        );

        $events = array_values(array_filter($events, function (array $e) use ($since, $sourceFilter, $categoryFilter) {
            if ($since !== null && $e['_ts'] !== null && $e['_ts']->lt($since)) {
                return false;
            }
            if ($sourceFilter !== null && $e['source'] !== $sourceFilter) {
                return false;
            }
            if ($categoryFilter !== null && $e['category'] !== $categoryFilter) {
                return false;
            }

            return true;
        }));

        usort($events, function (array $a, array $b) {
            $ta = $a['_ts']?->getTimestamp() ?? 0;
            $tb = $b['_ts']?->getTimestamp() ?? 0;
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }
            if ($a['source'] !== $b['source']) {
                return strcmp($a['source'], $b['source']);
            }

            return ($b['ref_id'] ?? 0) <=> ($a['ref_id'] ?? 0);
        });

        $events = array_slice($events, 0, $limit);

        return [
            'tenant_id' => $tenant->id,
            'count' => count($events),
            'events' => array_map(fn (array $e) => [
                'source' => $e['source'],
                'category' => $e['category'],
                'at' => optional($e['_ts'])->toIso8601String(),
                'code' => $e['code'],
                'summary' => $e['summary'],
                'ref_id' => $e['ref_id'] ?? null,
            ], $events),
        ];
    }

    private function event(string $source, string $category, ?Carbon $ts, string $code, string $summary, ?int $refId = null): array
    {
        return [
            'source' => $source,
            'category' => $category,
            '_ts' => $ts,
            'code' => $code,
            'summary' => $this->redactor->redactText($summary, 300),
            'ref_id' => $refId,
        ];
    }

    private function onboardingEvents(Tenant $tenant): array
    {
        return TenantProvisioningRun::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantProvisioningRun $r) => $this->event(
                'onboarding', 'onboarding', $r->created_at, 'provisioning_'.$r->status,
                'Provisioning run '.$r->status, $r->id,
            ))->all();
    }

    private function invoiceEvents(Tenant $tenant): array
    {
        return TenantBillingInvoice::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantBillingInvoice $i) => $this->event(
                'invoice', 'billing', $i->issued_at ?? $i->created_at, 'invoice_'.$i->collection_state,
                'Invoice '.$i->invoice_number.' ('.$i->status.'/'.$i->collection_state.')', $i->id,
            ))->all();
    }

    private function paymentIntentEvents(Tenant $tenant): array
    {
        return TenantBillingPaymentIntent::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantBillingPaymentIntent $p) => $this->event(
                'payment_intent', 'payment', $p->created_at, 'intent_'.$p->status,
                'Payment intent ('.$p->provider.'/'.$p->status.')', $p->id,
            ))->all();
    }

    private function gatewayEvents(Tenant $tenant): array
    {
        $invoiceIds = TenantBillingInvoice::query()->where('tenant_id', $tenant->id)->pluck('id')->all();
        if ($invoiceIds === []) {
            return [];
        }

        return TenantBillingGatewayEvent::query()->whereIn('invoice_id', $invoiceIds)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantBillingGatewayEvent $e) => $this->event(
                'gateway_event', 'payment', $e->occurred_at ?? $e->created_at, 'gateway_'.$e->normalized_status,
                'Gateway event '.$e->event_type.' ('.$e->normalized_status.')', $e->id,
            ))->all();
    }

    private function entitlementEvents(Tenant $tenant): array
    {
        return TenantEntitlementDecision::query()->forTenant($tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantEntitlementDecision $d) => $this->event(
                'entitlement_decision', 'entitlement', $d->created_at, 'entitlement_'.$d->decision,
                'Entitlement '.$d->decision.' for '.$d->entitlement_key.' ('.$d->reason_code.')', $d->id,
            ))->all();
    }

    private function deviceEvents(Tenant $tenant): array
    {
        return TenantDeviceActivation::query()->forTenant($tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantDeviceActivation $a) => $this->event(
                'device_activation', 'device', $a->updated_at ?? $a->created_at, 'device_'.$a->activation_status,
                'Device activation '.$a->activation_status, $a->id,
            ))->all();
    }

    private function syncBatchEvents(Tenant $tenant): array
    {
        return TenantAndroidSyncBatch::query()->forTenant($tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantAndroidSyncBatch $b) => $this->event(
                'sync_batch', 'sync', $b->created_at, 'sync_batch_'.$b->status,
                'Sync batch '.$b->status, $b->id,
            ))->all();
    }

    private function syncConflictEvents(Tenant $tenant): array
    {
        return TenantAndroidSyncItem::query()->forTenant($tenant->id)
            ->whereNotNull('conflict_code')->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantAndroidSyncItem $i) => $this->event(
                'sync_conflict', 'sync', $i->created_at, 'sync_conflict_'.$i->conflict_code,
                'Sync conflict ('.$i->conflict_code.')', $i->id,
            ))->all();
    }

    private function incidentEvents(Tenant $tenant): array
    {
        return TenantSupportIncident::query()->forTenant($tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantSupportIncident $i) => $this->event(
                'incident', $i->category, $i->opened_at ?? $i->created_at, 'incident_'.$i->status,
                'Incident '.$i->incident_number.' ('.$i->severity.'/'.$i->status.')', $i->id,
            ))->all();
    }

    private function supportActionEvents(Tenant $tenant): array
    {
        return TenantSupportAction::query()->forTenant($tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (TenantSupportAction $a) => $this->event(
                'support_action', 'support', $a->created_at, 'support_'.$a->action_type.'_'.$a->status,
                'Support action '.$a->action_type.' ('.$a->status.')', $a->id,
            ))->all();
    }

    private function adminAuditEvents(Tenant $tenant): array
    {
        return AdminAuditLog::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(50)->get()
            ->map(fn (AdminAuditLog $l) => $this->event(
                'admin_audit', 'support', $l->created_at, 'admin_'.strtolower((string) $l->action),
                'Admin action '.$l->action, $l->id,
            ))->all();
    }
}
