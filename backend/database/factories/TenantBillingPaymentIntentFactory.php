<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantBillingPaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantBillingPaymentIntent>
 */
class TenantBillingPaymentIntentFactory extends Factory
{
    protected $model = TenantBillingPaymentIntent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invoice_id' => TenantBillingInvoice::factory(),
            'provider' => 'mock',
            'channel' => 'mock_qris',
            'period_key' => '2026-07',
            'amount' => 99000,
            'currency' => 'IDR',
            'status' => TenantBillingPaymentIntent::STATUS_PENDING,
            'provider_reference' => 'MOCK-QRIS-'.strtoupper(Str::random(16)),
            'idempotency_key' => hash('sha256', 'intent:'.Str::uuid()->toString()),
            'source' => 'platform_admin',
            'metadata' => null,
        ];
    }
}
