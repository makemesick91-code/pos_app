<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceNumberGenerator $generator;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(InvoiceNumberGenerator::class);
        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
    }

    public function test_format_contains_store_code_and_date(): void
    {
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);

        $invoice = $this->generator->generate($this->tenant->id, $store);

        $this->assertSame('POS-A1-'.now()->format('Ymd').'-000001', $invoice);
    }

    public function test_same_store_increments_sequence(): void
    {
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);

        $first = $this->generator->generate($this->tenant->id, $store);
        Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'invoice_number' => $first,
            'sale_date' => now(),
        ]);

        $second = $this->generator->generate($this->tenant->id, $store);

        $this->assertStringEndsWith('-000001', $first);
        $this->assertStringEndsWith('-000002', $second);
    }

    public function test_different_stores_have_independent_sequences(): void
    {
        $storeA = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $storeB = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'B2']);

        Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $storeA->id,
            'invoice_number' => $this->generator->generate($this->tenant->id, $storeA),
            'sale_date' => now(),
        ]);

        $storeBInvoice = $this->generator->generate($this->tenant->id, $storeB);

        $this->assertSame('POS-B2-'.now()->format('Ymd').'-000001', $storeBInvoice);
    }

    public function test_invoice_is_scoped_per_tenant(): void
    {
        $tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);
        $storeA = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $storeB = Store::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'A1']);

        Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $storeA->id,
            'invoice_number' => $this->generator->generate($this->tenant->id, $storeA),
            'sale_date' => now(),
        ]);

        // A different tenant sharing the same store code still starts at 000001.
        $this->assertStringEndsWith('-000001', $this->generator->generate($tenantB->id, $storeB));
    }
}
