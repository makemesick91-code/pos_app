<?php

namespace App\Services\AndroidRuntime;

use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantManualSuspension;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sprint 34 — deterministic, isolated Android runtime SIMULATOR used by the
 * android-runtime:* simulate commands and the smoke script.
 *
 * It provisions its own throw-away tenant/store/device/cashier (never touching an
 * existing production tenant) and drives real activation + sync through the
 * canonical services so the scenarios exercise the true runtime gate. It never
 * prints a raw token, never marks an invoice paid, and never lifts a suspension.
 * Intended for an isolated (sqlite) database only.
 */
class AndroidRuntimeSimulator
{
    public function __construct(
        private readonly DeviceActivationService $activation,
        private readonly DeviceRevocationService $revocation,
        private readonly AndroidSyncIngestionService $ingestion,
    ) {}

    /**
     * Activate a device twice with the same fingerprint and prove idempotency
     * (ADR-R004): one activation, one device, second call returns the same row.
     *
     * @return array<string, mixed>
     */
    public function simulateActivation(): array
    {
        [$tenant, , $owner] = $this->provision();

        $fingerprint = 'fp-'.Str::random(24);
        $token = Str::random(40);
        $uuid = 'sim-device-'.$tenant->id;

        $first = $this->activation->activate($tenant, $token, $fingerprint, $uuid, 'Sim Device', $owner);
        $second = $this->activation->activate($tenant, $token, $fingerprint, $uuid, 'Sim Device', $owner);

        // Idempotency (ADR-R004): the second activation must reuse the same device
        // — exactly one RegisteredDevice for the activated uuid.
        $activatedDeviceCount = RegisteredDevice::query()->forTenant($tenant->id)->where('device_uuid', $uuid)->count();

        return [
            'tenant_id' => $tenant->id,
            'activation_id' => $first->id,
            'idempotent' => $first->id === $second->id,
            'device_count' => $activatedDeviceCount,
            'status' => $first->activation_status,
            // NB: the raw token is deliberately NOT included.
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function simulateSync(string $scenario): array
    {
        [$tenant, $store, $owner, $device] = $this->provision();
        $activation = $this->activation->resolveForDevice($device);
        $context = $this->context($tenant, $store, $owner);

        $items = fn (array $ids, string $type = 'inventory_snapshot') => array_map(
            fn (string $id) => ['client_item_id' => $id, 'item_type' => $type, 'action' => 'sync_snapshot', 'payload' => ['ref' => $id]],
            $ids,
        );

        switch ($scenario) {
            case 'valid':
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items(['i-'.Str::random(8), 'i-'.Str::random(8)]),
                ]));
                break;

            case 'replay':
                $batchId = 'sim-b-'.Str::random(12);
                $payload = ['client_batch_id' => $batchId, 'items' => $items(['i-'.Str::random(8)])];
                $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray($payload));
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray($payload));
                break;

            case 'duplicate-item':
                $itemId = 'i-'.Str::random(10);
                $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items([$itemId]),
                ]));
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items([$itemId]),
                ]));
                break;

            case 'conflict':
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => [
                        ['client_item_id' => 'i-'.Str::random(8), 'item_type' => 'inventory_snapshot', 'action' => 'sync_snapshot'],
                        ['client_item_id' => 'i-'.Str::random(8), 'item_type' => 'not_a_real_type', 'action' => 'create'],
                    ],
                ]));
                break;

            case 'revoked-device':
                $this->revocation->revoke($activation, $owner, 'simulation');
                $activation->refresh();
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items(['i-'.Str::random(8)]),
                ]));
                break;

            case 'suspended-tenant':
                TenantManualSuspension::query()->create([
                    'tenant_id' => $tenant->id,
                    'status' => TenantManualSuspension::STATUS_ACTIVE,
                    'reason' => 'simulation',
                    'effective_at' => Carbon::now(),
                ]);
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items(['i-'.Str::random(8)]),
                ]));
                break;

            case 'unpaid-past-grace':
                $this->makeOverdueInvoice($tenant);
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items(['i-'.Str::random(8)]),
                ]));
                break;

            case 'trial-expired':
                $this->makeExpiredTrial($tenant);
                $batch = $this->ingestion->ingest($context, $activation, AndroidSyncBatchData::fromArray([
                    'client_batch_id' => 'sim-b-'.Str::random(12),
                    'items' => $items(['i-'.Str::random(8)]),
                ]));
                break;

            default:
                return ['scenario' => $scenario, 'error' => 'unknown_scenario'];
        }

        $conflictCodes = $batch->items->pluck('conflict_code')->filter()->unique()->values()->all();

        return [
            'scenario' => $scenario,
            'batch_status' => $batch->status,
            'accepted' => $batch->accepted_count,
            'duplicate' => $batch->duplicate_count,
            'conflict' => $batch->conflict_count,
            'rejected' => $batch->rejected_count,
            'failed' => $batch->failed_count,
            'idempotent_replay' => (bool) ($batch->wasReplay ?? false),
            'conflict_codes' => $conflictCodes,
        ];
    }

    /**
     * @return array{0: Tenant, 1: Store, 2: User, 3: RegisteredDevice}
     */
    private function provision(): array
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        // The factory auto-registers a device (TenantFactory::AUTO_DEVICE_UUID).
        $device = RegisteredDevice::query()->forTenant($tenant->id)->active()->first()
            ?? RegisteredDevice::query()->create([
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'device_uuid' => 'sim-'.Str::random(16),
                'device_name' => 'Sim Device',
                'platform' => RegisteredDevice::PLATFORM_ANDROID,
                'registered_at' => now(),
                'last_seen_at' => now(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
            ]);

        return [$tenant, $store, $owner, $device];
    }

    private function context(Tenant $tenant, Store $store, User $user): TenantContext
    {
        $context = new TenantContext();
        $context->set($user, $tenant, $store);

        return $context;
    }

    private function makeOverdueInvoice(Tenant $tenant): void
    {
        $graceDays = (int) config('entitlement_governance.grace.unpaid_invoice_days', 7);
        $outstanding = (array) config('entitlement_governance.outstanding_collection_states', [TenantBillingInvoice::COLLECTION_OVERDUE]);

        TenantBillingInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'plan_key' => 'starter',
            'invoice_number' => 'SIM-'.Str::upper(Str::random(10)),
            'period_key' => now()->format('Y-m'),
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'issued_at' => now()->subDays($graceDays + 30),
            'due_at' => now()->subDays($graceDays + 20),
            'currency' => 'IDR',
            'subtotal_amount' => 99000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 99000,
            'status' => TenantBillingInvoice::STATUS_ISSUED,
            'collection_state' => $outstanding[0] ?? TenantBillingInvoice::COLLECTION_OVERDUE,
            'source' => 'simulation',
            'idempotency_key' => 'sim-inv-'.Str::random(16),
        ]);
    }

    private function makeExpiredTrial(Tenant $tenant): void
    {
        $tenant->tenantSubscriptions()->delete();

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $this->anyPlanId(),
            'status' => TenantSubscription::STATUS_TRIAL,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    private function anyPlanId(): int
    {
        $plan = \App\Models\SubscriptionPlan::query()->firstOrCreate(
            ['code' => \App\Models\SubscriptionPlan::CODE_STARTER],
            ['name' => 'Starter', 'price_monthly' => 99000, 'max_stores' => 1, 'max_devices' => 3, 'is_active' => true],
        );

        return $plan->id;
    }
}
