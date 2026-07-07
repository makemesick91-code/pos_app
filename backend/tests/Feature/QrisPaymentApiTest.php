<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 5 — QRIS payment creation. Proves the backend-driven QRIS flow: a
 * tenant can mint a PENDING QRIS for an unpaid sale (moving the sale to PENDING),
 * but never for a paid/cancelled sale, and never with a disabled provider.
 */
class QrisPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
    }

    private function unpaidSale(array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'grand_total' => 20000,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
        ], $overrides));
    }

    public function test_tenant_can_create_qris_payment_for_own_unpaid_sale(): void
    {
        $sale = $this->unpaidSale();

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated()
            ->assertJsonPath('data.sale_id', $sale->id)
            ->assertJsonPath('data.method', 'QRIS')
            ->assertJsonPath('data.provider', 'FAKE')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.amount', '20000.00')
            ->assertJsonPath('data.sale_payment_status', 'PENDING')
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');

        $this->assertNotEmpty($response->json('data.provider_reference'));
        $this->assertStringContainsString('FAKE-QRIS', $response->json('data.qr_payload'));
        $this->assertNotNull($response->json('data.expired_at'));

        $sale->refresh();
        $this->assertSame(Sale::PAYMENT_STATUS_PENDING, $sale->payment_status);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'method' => 'QRIS',
            'provider' => 'FAKE',
            'status' => 'PENDING',
        ]);
    }

    public function test_default_provider_is_used_when_none_given(): void
    {
        $sale = $this->unpaidSale();

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'FAKE')
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_cannot_create_qris_for_already_paid_sale(): void
    {
        $sale = $this->unpaidSale(['payment_status' => Sale::PAYMENT_STATUS_PAID]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('payments', [
            'sale_id' => $sale->id,
            'method' => 'QRIS',
        ]);
    }

    public function test_cannot_create_qris_for_cancelled_sale(): void
    {
        $sale = $this->unpaidSale(['payment_status' => Sale::PAYMENT_STATUS_CANCELLED]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertStatus(422);
    }

    public function test_disabled_provider_is_rejected(): void
    {
        $sale = $this->unpaidSale();

        // midtrans is disabled by default in config/payment_gateway.php.
        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'midtrans'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('provider');

        $this->assertDatabaseMissing('payments', [
            'sale_id' => $sale->id,
            'method' => 'QRIS',
        ]);
    }

    public function test_repeated_request_reuses_active_pending_qris(): void
    {
        $sale = $this->unpaidSale();

        $first = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated();

        $second = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payments', 1);
    }
}
