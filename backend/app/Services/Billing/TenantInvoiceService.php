<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 30 — orchestrates tenant invoice generation and the document lifecycle
 * (BIL-R002/R003/R004/R005).
 *
 * Generation is idempotent per tenant + billing period: if a live (non-void,
 * non-cancelled) invoice already exists for the period it is returned unchanged,
 * so retries — and a later subscription-renewal run — never create a duplicate.
 * The amount is taken from the plan pricing source of truth, never from client
 * input. A tenant with no plan pricing is refused, never silently zeroed.
 */
class TenantInvoiceService
{
    public function __construct(
        private readonly BillingPeriodService $periods,
        private readonly TenantInvoicePricingService $pricing,
        private readonly TenantInvoiceNumberGenerator $numbers,
        private readonly TenantInvoiceStatusService $status,
        private readonly BillingMetadataSanitizer $sanitizer,
        private readonly BillingAuditService $audit,
    ) {}

    /**
     * Idempotently generate (and issue) the invoice for a tenant + period key.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function generate(
        Tenant $tenant,
        string $periodKey,
        string $source = 'platform_admin',
        ?User $actor = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): TenantBillingInvoice {
        $period = $this->periods->resolveForKey($periodKey);
        $price = $this->pricing->resolveForTenant($tenant);

        return DB::transaction(function () use ($tenant, $period, $price, $source, $actor, $metadata, $request) {
            // BIL-R002/R005 — one live invoice per tenant + period, any source.
            $existing = TenantBillingInvoice::query()
                ->where('tenant_id', $tenant->id)
                ->where('period_key', $period->key)
                ->whereNotIn('status', [TenantBillingInvoice::STATUS_VOID, TenantBillingInvoice::STATUS_CANCELLED])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof TenantBillingInvoice) {
                return $existing;
            }

            $planId = optional(TenantPlan::query()->where('key', $price['plan_key'])->first())->id;

            $invoice = new TenantBillingInvoice([
                'tenant_id' => $tenant->id,
                'tenant_plan_id' => $planId,
                'plan_key' => $price['plan_key'],
                'invoice_number' => $this->numbers->generate($tenant, $period->key),
                'period_key' => $period->key,
                'period_start' => $period->start,
                'period_end' => $period->end,
                'due_at' => $period->dueAt,
                'currency' => $price['currency'],
                'subtotal_amount' => $price['amount'],
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $price['amount'],
                'status' => TenantBillingInvoice::STATUS_DRAFT,
                'collection_state' => TenantBillingInvoice::COLLECTION_NOT_DUE,
                'source' => $source,
                'idempotency_key' => $this->idempotencyKey($tenant, $period->key, $source),
                'metadata' => $this->sanitizer->sanitize($metadata),
            ]);
            $invoice->save();

            // Issue immediately, then set the collection axis from the due date.
            $this->status->issue($invoice);
            $this->status->refreshCollectionState($invoice);

            $this->audit->record(
                actor: $actor,
                action: 'billing.invoice.generated',
                targetType: TenantBillingInvoice::class,
                targetId: $invoice->id,
                tenantId: $tenant->id,
                after: $this->auditSnapshot($invoice),
                metadata: ['source' => $source, 'period_key' => $period->key],
                request: $request,
            );

            return $invoice;
        });
    }

    public function void(TenantBillingInvoice $invoice, ?User $actor = null, ?string $reason = null, ?Request $request = null): TenantBillingInvoice
    {
        $before = $this->auditSnapshot($invoice);
        $invoice = $this->status->void($invoice);

        $this->audit->record(
            actor: $actor,
            action: 'billing.invoice.voided',
            targetType: TenantBillingInvoice::class,
            targetId: $invoice->id,
            tenantId: $invoice->tenant_id,
            before: $before,
            after: $this->auditSnapshot($invoice),
            metadata: ['reason' => $reason],
            request: $request,
        );

        return $invoice;
    }

    public function cancel(TenantBillingInvoice $invoice, ?User $actor = null, ?string $reason = null, ?Request $request = null): TenantBillingInvoice
    {
        $before = $this->auditSnapshot($invoice);
        $invoice = $this->status->cancel($invoice);

        $this->audit->record(
            actor: $actor,
            action: 'billing.invoice.cancelled',
            targetType: TenantBillingInvoice::class,
            targetId: $invoice->id,
            tenantId: $invoice->tenant_id,
            before: $before,
            after: $this->auditSnapshot($invoice),
            metadata: ['reason' => $reason],
            request: $request,
        );

        return $invoice;
    }

    private function idempotencyKey(Tenant $tenant, string $periodKey, string $source): string
    {
        return hash('sha256', "invoice:{$tenant->id}:{$periodKey}:{$source}");
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(TenantBillingInvoice $invoice): array
    {
        return [
            'invoice_number' => $invoice->invoice_number,
            'period_key' => $invoice->period_key,
            'plan_key' => $invoice->plan_key,
            'total_amount' => $invoice->total_amount,
            'currency' => $invoice->currency,
            'status' => $invoice->status,
            'collection_state' => $invoice->collection_state,
        ];
    }
}
