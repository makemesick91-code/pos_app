<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashPaymentApiTest extends TestCase
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

    public function test_cash_payment_finalizes_unpaid_sale(): void
    {
        $sale = $this->unpaidSale(['grand_total' => 20000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/cash", ['paid_amount' => 25000])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.paid_total', '25000.00')
            ->assertJsonPath('data.change_total', '5000.00');

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'method' => 'CASH',
            'provider' => 'MANUAL',
            'status' => 'PAID',
        ]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'payment_status' => 'PAID']);
    }

    public function test_cannot_cash_pay_cancelled_sale(): void
    {
        $sale = $this->unpaidSale(['payment_status' => Sale::PAYMENT_STATUS_CANCELLED]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/cash", ['paid_amount' => 25000])
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_double_pay_already_paid_sale(): void
    {
        $sale = $this->unpaidSale(['payment_status' => Sale::PAYMENT_STATUS_PAID]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/cash", ['paid_amount' => 25000])
            ->assertStatus(422);
    }

    public function test_cash_payment_must_cover_grand_total(): void
    {
        $sale = $this->unpaidSale(['grand_total' => 20000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/cash", ['paid_amount' => 10000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('paid_amount');
    }

    public function test_client_cannot_force_amount_or_status(): void
    {
        $sale = $this->unpaidSale(['grand_total' => 20000]);

        $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/sales/{$sale->id}/payments/cash", [
                'paid_amount' => 25000,
                'amount' => 1,
                'status' => 'PAID',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'status']);
    }
}
