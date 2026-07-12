<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPayment;
use App\Models\TenantBillingPaymentIntent;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * UIX-5 test fixtures for the billing console. Builds canonical
 * tenant_billing_* rows (Sprint 30/31) so tests exercise the real read path.
 */
trait BuildsBillingData
{
    protected function makeTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create($overrides);
    }

    protected function makeOwner(Tenant $tenant, array $overrides = []): User
    {
        return User::factory()->tenantOwner()->create(array_merge(['tenant_id' => $tenant->id], $overrides));
    }

    protected function makePlatformAdmin(array $overrides = []): User
    {
        return User::factory()->platformAdmin()->create($overrides);
    }

    protected function makeInvoice(Tenant $tenant, array $overrides = []): TenantBillingInvoice
    {
        return TenantBillingInvoice::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $overrides));
    }

    protected function makePayment(TenantBillingInvoice $invoice, int $amount, string $status): TenantBillingPayment
    {
        return TenantBillingPayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'PAY-'.strtoupper(Str::random(10)),
            'amount' => $amount,
            'currency' => 'IDR',
            'method' => 'manual',
            'status' => $status,
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
            'received_at' => now(),
            'source' => 'platform_admin',
            'idempotency_key' => hash('sha256', 'payment:'.Str::uuid()->toString()),
        ]);
    }

    protected function makeIntent(TenantBillingInvoice $invoice, string $status): TenantBillingPaymentIntent
    {
        return TenantBillingPaymentIntent::factory()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'status' => $status,
            'amount' => (int) $invoice->total_amount,
        ]);
    }
}
