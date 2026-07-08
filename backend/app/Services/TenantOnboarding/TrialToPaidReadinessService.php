<?php

namespace App\Services\TenantOnboarding;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use App\Services\Billing\BillingPeriodService;
use App\Services\Billing\TenantInvoiceService;
use App\Services\PaymentGateway\PaymentGatewayIntentService;

/**
 * Sprint 33 — prepares the trial-to-paid transition WITHOUT ever marking a paid
 * state (ONB-R014/R015/R016). It:
 *  - generates the first invoice through the Sprint 30 TenantInvoiceService
 *    (idempotent per tenant+period; amount from plan pricing, never client),
 *  - creates a QRIS/mock payment intent through the Sprint 31 gateway service.
 *
 * It NEVER records a payment, never marks an invoice paid, and never unlocks
 * paid entitlement. Paid access only ever follows the trusted Sprint 30
 * collection state consumed by the Sprint 32 EntitlementBillingStateService.
 */
class TrialToPaidReadinessService
{
    public function __construct(
        private readonly TenantInvoiceService $invoices,
        private readonly PaymentGatewayIntentService $intents,
        private readonly BillingPeriodService $periods,
    ) {}

    public function generateInvoice(Tenant $tenant, ?User $actor = null, ?string $periodKey = null): TenantBillingInvoice
    {
        $periodKey ??= $this->periods->resolveForDate()->key;

        return $this->invoices->generate(
            tenant: $tenant,
            periodKey: $periodKey,
            source: 'system',
            actor: $actor,
            metadata: ['origin' => 'sprint33_onboarding'],
        );
    }

    public function createPaymentIntent(TenantBillingInvoice $invoice, ?User $actor = null, ?string $idempotencyKey = null): TenantBillingPaymentIntent
    {
        return $this->intents->create(
            invoice: $invoice,
            provider: null,
            channel: null,
            actor: $actor,
            source: 'system',
            metadata: ['origin' => 'sprint33_onboarding'],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * A safe, redacted transition summary. Reflects only the trusted collection
     * axis; never asserts paid unless the invoice's own collection_state is paid.
     *
     * @return array<string, mixed>
     */
    public function summary(TenantBillingInvoice $invoice): array
    {
        return [
            'invoice_status' => $invoice->status,
            'collection_state' => $invoice->collection_state,
            'is_paid' => $invoice->collection_state === TenantBillingInvoice::COLLECTION_PAID,
            'period_key' => $invoice->period_key,
        ];
    }
}
