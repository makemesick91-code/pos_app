<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Sprint 31 — convenience factory for tests/summaries. Real invoices are produced
 * by TenantInvoiceService (plan-priced, idempotent); this factory only builds a
 * plausible, self-consistent row for gateway tests that do not need generation.
 *
 * @extends Factory<TenantBillingInvoice>
 */
class TenantBillingInvoiceFactory extends Factory
{
    protected $model = TenantBillingInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tenant_plan_id' => null,
            'plan_key' => 'starter',
            'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
            'period_key' => '2026-07',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
            'currency' => 'IDR',
            'subtotal_amount' => 99000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 99000,
            'status' => TenantBillingInvoice::STATUS_ISSUED,
            'collection_state' => TenantBillingInvoice::COLLECTION_PENDING,
            'source' => 'platform_admin',
            'idempotency_key' => hash('sha256', 'invoice:'.Str::uuid()->toString()),
            'metadata' => null,
        ];
    }
}
