<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 6 — receipt preview. Proves receipt eligibility (FINAL only when PAID),
 * that receipts read the immutable sale_item snapshot (never the live catalog),
 * that totals mirror the sale, and that no gateway secret ever leaks.
 */
class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->store = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'A1',
            'name' => 'Store A1',
            'address' => 'Jl. Contoh No. 1',
        ]);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'name' => 'Kasir A',
        ]);
    }

    private function sale(string $paymentStatus, array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 20000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20000,
            'paid_total' => 20000,
            'change_total' => 0,
            'payment_status' => $paymentStatus,
        ], $overrides));
    }

    private function addItem(Sale $sale, array $overrides = []): SaleItem
    {
        return SaleItem::factory()->create(array_merge([
            'tenant_id' => $sale->tenant_id,
            'store_id' => $sale->store_id,
            'sale_id' => $sale->id,
            'product_name' => 'Produk A',
            'product_sku' => 'SKU-A-001',
            'unit' => 'pcs',
            'qty' => 2,
            'unit_price' => 10000,
            'discount' => 0,
            'subtotal' => 20000,
        ], $overrides));
    }

    private function addCashPayment(Sale $sale): Payment
    {
        return Payment::factory()->create([
            'tenant_id' => $sale->tenant_id,
            'store_id' => $sale->store_id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_CASH,
            'provider' => Payment::PROVIDER_MANUAL,
            'status' => Payment::STATUS_PAID,
            'amount' => 20000,
        ]);
    }

    private function addQrisPayment(Sale $sale, string $status, ?string $rawResponse = null): Payment
    {
        return Payment::factory()->create([
            'tenant_id' => $sale->tenant_id,
            'store_id' => $sale->store_id,
            'sale_id' => $sale->id,
            'method' => Payment::METHOD_QRIS,
            'provider' => Payment::PROVIDER_FAKE,
            'status' => $status,
            'amount' => 20000,
            'raw_response' => $rawResponse,
            'paid_at' => $status === Payment::STATUS_PAID ? now() : null,
        ]);
    }

    public function test_cash_paid_sale_returns_final_printable_receipt(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID);
        $this->addItem($sale);
        $this->addCashPayment($sale);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.sale_id', $sale->id)
            ->assertJsonPath('data.receipt_status', 'FINAL')
            ->assertJsonPath('data.printable', true)
            ->assertJsonPath('data.print_block_reason', null)
            ->assertJsonPath('data.store.name', 'Store A1')
            ->assertJsonPath('data.cashier.name', 'Kasir A')
            ->assertJsonPath('data.payments.0.method', 'CASH')
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');
    }

    public function test_qris_paid_sale_returns_final_printable_receipt(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID);
        $this->addItem($sale);
        $this->addQrisPayment($sale, Payment::STATUS_PAID);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt_status', 'FINAL')
            ->assertJsonPath('data.printable', true)
            ->assertJsonPath('data.payments.0.method', 'QRIS')
            ->assertJsonPath('data.payments.0.provider', 'FAKE')
            ->assertJsonPath('data.payments.0.status', 'PAID');
    }

    public function test_qris_pending_sale_is_not_printable(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PENDING, [
            'paid_total' => 0,
        ]);
        $this->addItem($sale);
        $this->addQrisPayment($sale, Payment::STATUS_PENDING);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt_status', 'DRAFT')
            ->assertJsonPath('data.printable', false)
            ->assertJsonPath('data.print_block_reason', fn ($reason) => is_string($reason) && $reason !== '');
    }

    public function test_unpaid_sale_is_not_printable(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_UNPAID, ['paid_total' => 0]);
        $this->addItem($sale);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt_status', 'NOT_PRINTABLE')
            ->assertJsonPath('data.printable', false);
    }

    public function test_cancelled_sale_is_not_printable(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_CANCELLED);
        $this->addItem($sale);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.receipt_status', 'NOT_PRINTABLE')
            ->assertJsonPath('data.printable', false);
    }

    public function test_expired_and_failed_sales_are_not_printable(): void
    {
        foreach ([Sale::PAYMENT_STATUS_EXPIRED, Sale::PAYMENT_STATUS_FAILED] as $status) {
            $sale = $this->sale($status, ['paid_total' => 0]);
            $this->addItem($sale);

            $this->actingAs($this->cashier, 'sanctum')
                ->getJson("/api/v1/sales/{$sale->id}/receipt")
                ->assertOk()
                ->assertJsonPath('data.printable', false)
                ->assertJsonPath('data.receipt_status', 'NOT_PRINTABLE');
        }
    }

    public function test_receipt_uses_sale_item_snapshot_not_current_product(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID);

        // A live product whose name/price were later changed away from checkout.
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nama Baru Setelah Edit',
        ]);

        $this->addItem($sale, [
            'product_id' => $product->id,
            'product_name' => 'Produk Snapshot',
            'unit_price' => 10000,
        ]);
        $this->addCashPayment($sale);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Produk Snapshot')
            ->assertJsonPath('data.items.0.unit_price', '10000.00');
    }

    public function test_receipt_totals_match_sale_totals(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID, [
            'subtotal' => 25000,
            'discount_total' => 5000,
            'grand_total' => 20000,
            'paid_total' => 20000,
            'change_total' => 0,
        ]);
        $this->addItem($sale);
        $this->addCashPayment($sale);

        $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '25000.00')
            ->assertJsonPath('data.totals.discount_total', '5000.00')
            ->assertJsonPath('data.totals.grand_total', '20000.00')
            ->assertJsonPath('data.totals.paid_total', '20000.00')
            ->assertJsonPath('data.totals.change_total', '0.00');
    }

    public function test_receipt_does_not_expose_raw_response_or_secrets(): void
    {
        $sale = $this->sale(Sale::PAYMENT_STATUS_PAID);
        $this->addItem($sale);
        $this->addQrisPayment(
            $sale,
            Payment::STATUS_PAID,
            json_encode(['secret_gateway_key' => 'SUPER_SECRET_TOKEN_XYZ'])
        );

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson("/api/v1/sales/{$sale->id}/receipt")
            ->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsString('raw_response', $body);
        $this->assertStringNotContainsString('SUPER_SECRET_TOKEN_XYZ', $body);
    }
}
