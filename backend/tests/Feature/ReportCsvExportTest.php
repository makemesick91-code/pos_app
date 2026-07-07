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
 * Sprint 9 — the daily sales CSV export uses the same tenant-isolated,
 * backend-authoritative figures as the JSON endpoint. It emits a header row and
 * never exposes raw gateway payloads or secrets.
 */
class ReportCsvExportTest extends TestCase
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

    private function paidCashSale(float $amount): void
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
            'raw_response' => 'SECRET-GATEWAY-PAYLOAD',
        ]);
    }

    public function test_export_returns_csv_with_header_row(): void
    {
        $this->paidCashSale(100000);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/daily-sales/export.csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString(
            'business_date,store_id,sales_count,cancelled_sales_count,gross_total,discount_total,tax_total,grand_total,paid_total,change_total',
            $body,
        );
        $this->assertStringContainsString('100000.00', $body);
    }

    public function test_export_does_not_expose_gateway_secrets(): void
    {
        $this->paidCashSale(100000);

        $body = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/daily-sales/export.csv')
            ->streamedContent();

        $this->assertStringNotContainsString('SECRET-GATEWAY-PAYLOAD', $body);
        $this->assertStringNotContainsString('raw_response', $body);
    }

    public function test_export_follows_store_filter(): void
    {
        $otherStore = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A2']);
        $this->paidCashSale(100000);

        $body = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/daily-sales/export.csv?store_id='.$otherStore->id)
            ->streamedContent();

        // Other store has no sales — the data row shows zero revenue.
        $this->assertStringNotContainsString('100000.00', $body);
        $this->assertStringContainsString('0.00', $body);
    }
}
