<?php

namespace Database\Factories;

use App\Models\TenantBillingGatewayEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantBillingGatewayEvent>
 */
class TenantBillingGatewayEventFactory extends Factory
{
    protected $model = TenantBillingGatewayEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'mock',
            'event_type' => 'payment.paid',
            'provider_event_id' => 'evt_'.Str::random(12),
            'provider_reference' => 'MOCK-QRIS-'.strtoupper(Str::random(16)),
            'payload_hash' => hash('sha256', Str::uuid()->toString()),
            'signature_hash' => substr(hash('sha256', Str::random(20)), 0, 32),
            'signature_verified' => true,
            'status' => TenantBillingGatewayEvent::STATUS_PROCESSED,
            'normalized_status' => 'paid',
            'amount' => 99000,
            'currency' => 'IDR',
            'occurred_at' => now(),
            'processed_at' => now(),
            'metadata' => null,
        ];
    }
}
