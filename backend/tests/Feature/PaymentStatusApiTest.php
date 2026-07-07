<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 5 — payment status endpoint. A tenant may poll only its own payment,
 * and the response carries the related sale's payment_status.
 */
class PaymentStatusApiTest extends TestCase
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

    private function pendingQris(): Payment
    {
        $sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'grand_total' => 15000,
            'payment_status' => Sale::PAYMENT_STATUS_UNPAID,
        ]);

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/qris", ['provider' => 'fake'])
            ->assertCreated();

        return Payment::findOrFail($response->json('data.id'));
    }

    public function test_tenant_can_read_own_payment_status(): void
    {
        $payment = $this->pendingQris();

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}/status")
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.method', 'QRIS')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.sale_payment_status', 'PENDING')
            ->assertJsonMissingPath('data.raw_response');
    }

    public function test_tenant_cannot_read_other_tenant_payment_status(): void
    {
        $payment = $this->pendingQris();

        $otherTenant = Tenant::factory()->create(['code' => 'TENANT-B']);
        $otherStore = Store::factory()->create(['tenant_id' => $otherTenant->id, 'code' => 'B1']);
        $otherCashier = User::factory()->cashier()->create([
            'tenant_id' => $otherTenant->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($otherCashier, 'sanctum')
            ->getJson("/api/v1/payments/{$payment->id}/status")
            ->assertNotFound();
    }
}
