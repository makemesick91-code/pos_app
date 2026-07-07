<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 9 — daily closing snapshot. Totals are computed by the backend; client
 * totals are ignored; exactly one closing exists per tenant/store/business_date;
 * a duplicate close replays the existing snapshot without inserting a new row.
 */
class DailyClosingApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => User::ROLE_STORE_ADMIN,
        ]);
    }

    private function paidCashSale(float $amount): Sale
    {
        $sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'sale_date' => now(),
            'payment_status' => Sale::PAYMENT_STATUS_PAID,
            'grand_total' => $amount,
            'paid_total' => $amount,
        ]);
        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_PAID,
            'amount' => $amount,
        ]);

        return $sale;
    }

    public function test_can_create_closing_with_backend_calculated_totals(): void
    {
        $this->paidCashSale(70000);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', [
                'store_id' => $this->store->id,
                'business_date' => now()->toDateString(),
                'notes' => 'Closing harian',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', DailyClosing::STATUS_CLOSED)
            ->assertJsonPath('data.sales_count', 1)
            ->assertJsonPath('data.cash_total', '70000.00')
            ->assertJsonPath('data.grand_total', '70000.00')
            ->assertJsonPath('meta.duplicate_replay', false);

        $this->assertDatabaseHas('daily_closings', [
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'closed_by' => $this->user->id,
            'grand_total' => '70000.00',
        ]);
    }

    public function test_client_provided_totals_are_ignored(): void
    {
        $this->paidCashSale(10000);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', [
                'store_id' => $this->store->id,
                'business_date' => now()->toDateString(),
                'grand_total' => 999999,
                'cash_total' => 999999,
                'sales_count' => 999,
            ])
            ->assertCreated()
            ->assertJsonPath('data.grand_total', '10000.00')
            ->assertJsonPath('data.cash_total', '10000.00')
            ->assertJsonPath('data.sales_count', 1);
    }

    public function test_duplicate_closing_replays_existing_row(): void
    {
        $this->paidCashSale(10000);
        $payload = [
            'store_id' => $this->store->id,
            'business_date' => now()->toDateString(),
        ];

        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', $payload)
            ->assertOk()
            ->assertJsonPath('meta.duplicate_replay', true)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, DailyClosing::query()->count());
    }

    public function test_future_business_date_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', [
                'store_id' => $this->store->id,
                'business_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('business_date');
    }

    public function test_can_list_and_show_closings(): void
    {
        $this->paidCashSale(10000);
        $id = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/closings/daily', [
                'store_id' => $this->store->id,
                'business_date' => now()->toDateString(),
            ])
            ->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/closings/daily')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/closings/daily/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }
}
