<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RegisteredDevice;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantAndroidSyncBatch;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\AndroidRuntime\AndroidSyncBatchData;
use App\Services\AndroidRuntime\AndroidSyncIngestionService;
use App\Services\AndroidRuntime\DeviceActivationService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 34 — AndroidSyncIngestionService idempotency + runtime gating
 * (ADR-R012..R016, R026).
 */
class AndroidSyncIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'ADR-SYNC']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->cashier = User::factory()->cashier()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
        ]);
    }

    private function ingestion(): AndroidSyncIngestionService
    {
        return app(AndroidSyncIngestionService::class);
    }

    private function context(): TenantContext
    {
        $ctx = new TenantContext();
        $ctx->set($this->cashier, $this->tenant, $this->store);

        return $ctx;
    }

    private function activation()
    {
        $device = RegisteredDevice::query()->forTenant($this->tenant->id)->active()->firstOrFail();

        return app(DeviceActivationService::class)->resolveForDevice($device);
    }

    private function snapshotItems(array $ids): array
    {
        return array_map(fn ($id) => [
            'client_item_id' => $id,
            'item_type' => 'inventory_snapshot',
            'action' => 'sync_snapshot',
            'payload' => ['ref' => $id],
        ], $ids);
    }

    public function test_valid_batch_is_accepted(): void
    {
        $batch = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-valid-1',
            'items' => $this->snapshotItems(['a1', 'a2']),
        ]));

        $this->assertSame(TenantAndroidSyncBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(2, $batch->accepted_count);
    }

    public function test_replay_same_batch_is_idempotent(): void
    {
        $payload = ['client_batch_id' => 'batch-replay-1', 'items' => $this->snapshotItems(['r1'])];
        $first = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray($payload));
        $second = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray($payload));

        $this->assertSame($first->id, $second->id);
        $this->assertTrue((bool) ($second->wasReplay ?? false));
        $this->assertSame(1, TenantAndroidSyncBatch::query()->where('client_batch_id', 'batch-replay-1')->count());
    }

    public function test_duplicate_client_item_is_not_double_mutated(): void
    {
        $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-dup-1',
            'items' => $this->snapshotItems(['dup-item']),
        ]));

        $second = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-dup-2',
            'items' => $this->snapshotItems(['dup-item']),
        ]));

        $this->assertSame(1, $second->duplicate_count);
        $this->assertSame(0, $second->accepted_count);
    }

    public function test_sale_sync_is_idempotent_no_duplicate(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'selling_price' => 10000]);

        $saleItem = [
            'client_item_id' => 'sale-uuid-001',
            'item_type' => 'sale',
            'action' => 'create',
            'payload' => [
                'items' => [['product_id' => $product->id, 'qty' => 2]],
                'payment' => ['method' => 'CASH', 'paid_amount' => 20000],
            ],
        ];

        // Two separate batches carrying the SAME client_item_id → one sale only.
        $first = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'sale-batch-1', 'items' => [$saleItem],
        ]));
        $second = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'sale-batch-2', 'items' => [$saleItem],
        ]));

        $this->assertSame(1, $first->accepted_count);
        $this->assertSame(1, $second->duplicate_count);
        $this->assertSame(1, Sale::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_revoked_device_sync_is_denied(): void
    {
        $activation = $this->activation();
        $activation->forceFill(['activation_status' => 'revoked', 'revoked_at' => Carbon::now()])->save();

        $batch = $this->ingestion()->ingest($this->context(), $activation->fresh(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-revoked-1',
            'items' => $this->snapshotItems(['x1']),
        ]));

        $this->assertSame(TenantAndroidSyncBatch::STATUS_REJECTED, $batch->status);
        $this->assertContains('device_revoked', $batch->items->pluck('conflict_code')->all());
    }

    public function test_suspended_tenant_sync_is_denied(): void
    {
        TenantManualSuspension::query()->create([
            'tenant_id' => $this->tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'hold',
            'effective_at' => Carbon::now(),
        ]);

        $batch = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-susp-1',
            'items' => $this->snapshotItems(['s1']),
        ]));

        $this->assertSame(TenantAndroidSyncBatch::STATUS_REJECTED, $batch->status);
        $this->assertContains('tenant_suspended', $batch->items->pluck('conflict_code')->all());
    }

    public function test_payment_item_is_never_applied_as_settlement(): void
    {
        $batch = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-pay-1',
            'items' => [[
                'client_item_id' => 'pay-1',
                'item_type' => 'payment',
                'action' => 'create',
                'payload' => ['amount' => 999999],
            ]],
        ]));

        $item = $batch->items->firstWhere('client_item_id', 'pay-1');
        $this->assertSame('skipped', $item->status);
    }

    public function test_batch_output_is_redacted(): void
    {
        TenantManualSuspension::query()->create([
            'tenant_id' => $this->tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'hold',
            'effective_at' => Carbon::now(),
        ]);

        $batch = $this->ingestion()->ingest($this->context(), $this->activation(), AndroidSyncBatchData::fromArray([
            'client_batch_id' => 'batch-redact-1',
            'items' => [[
                'client_item_id' => 'ri-1',
                'item_type' => 'sale',
                'action' => 'create',
                'payload' => ['password' => 'super-secret', 'token' => 'abc123'],
            ]],
        ]));

        $encoded = json_encode($batch->metadata_json).json_encode($batch->items->map->toSafeArray()->all());
        $this->assertStringNotContainsString('super-secret', $encoded);
        $this->assertStringNotContainsString('abc123', $encoded);
    }
}
