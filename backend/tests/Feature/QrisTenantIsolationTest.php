<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Sprint 5 QRIS isolation gate: tenant A can never create or read tenant B's
 * QRIS payments, and a webhook resolved purely by provider_reference still
 * settles only the correct tenant's payment.
 */
class QrisTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Store $storeA;
    private Store $storeB;
    private User $cashierA;
    private User $cashierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $this->storeA = Store::factory()->create(['tenant_id' => $this->tenantA->id, 'code' => 'A1']);
        $this->storeB = Store::factory()->create(['tenant_id' => $this->tenantB->id, 'code' => 'B1']);
        $this->cashierA = User::factory()->cashier()->create([
            'tenant_id' => $this->tenantA->id,
            'store_id' => $this->storeA->id,
        ]);
        $this->cashierB = User::factory()->cashier()->create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeB->id,
        ]);
    }

    private function unpaidSale(Tenant $tenant, Store $store, User $cashier): Sale
    {
        return Sale::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'cashier_id' => $cashier->id,
            'grand_total' => 20000,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
        ]);
    }

    private function qrisFor(User $cashier, Sale $sale): Payment
    {
        $response = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated();

        return Payment::findOrFail($response->json('data.id'));
    }

    private function postWebhook(string $reference): TestResponse
    {
        $raw = json_encode(['provider_reference' => $reference, 'status' => 'PAID']);
        $secret = (string) config('payment_gateway.providers.fake.webhook_secret');

        return $this->call('POST', '/api/v1/webhooks/payments/fake', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FAKE_QRIS_SIGNATURE' => hash_hmac('sha256', $raw, $secret),
        ], $raw);
    }

    public function test_tenant_a_cannot_create_qris_for_tenant_b_sale(): void
    {
        $saleB = $this->unpaidSale($this->tenantB, $this->storeB, $this->cashierB);

        $this->actingAs($this->cashierA, 'sanctum')
            ->postJson("/api/v1/sales/{$saleB->id}/payments/qris", ['provider' => 'fake'])
            ->assertNotFound();

        $this->assertDatabaseMissing('payments', ['sale_id' => $saleB->id, 'method' => 'QRIS']);
    }

    public function test_tenant_a_cannot_read_tenant_b_payment_status(): void
    {
        $saleB = $this->unpaidSale($this->tenantB, $this->storeB, $this->cashierB);
        $paymentB = $this->qrisFor($this->cashierB, $saleB);

        $this->actingAs($this->cashierA, 'sanctum')
            ->getJson("/api/v1/payments/{$paymentB->id}/status")
            ->assertNotFound();
    }

    public function test_webhook_settles_only_the_matching_tenant_payment(): void
    {
        $saleA = $this->unpaidSale($this->tenantA, $this->storeA, $this->cashierA);
        $saleB = $this->unpaidSale($this->tenantB, $this->storeB, $this->cashierB);
        $paymentA = $this->qrisFor($this->cashierA, $saleA);
        $paymentB = $this->qrisFor($this->cashierB, $saleB);

        // A webhook carrying tenant B's reference must only settle tenant B.
        $this->postWebhook($paymentB->provider_reference)->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $paymentB->fresh()->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $saleB->fresh()->payment_status);

        $this->assertSame(Payment::STATUS_PENDING, $paymentA->fresh()->status);
        $this->assertSame(Sale::PAYMENT_STATUS_PENDING, $saleA->fresh()->payment_status);

        // The webhook log is attributed to tenant B, never tenant A.
        $this->assertDatabaseHas('payment_webhook_logs', [
            'payment_id' => $paymentB->id,
            'tenant_id' => $this->tenantB->id,
        ]);
    }
}
