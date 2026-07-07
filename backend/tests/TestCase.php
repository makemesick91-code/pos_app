<?php

namespace Tests;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Database\Factories\TenantFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sprint 10 — the subscription/device gate expects an X-Device-UUID header on
     * protected business API calls. Factory tenants auto-register a device with
     * TenantFactory::AUTO_DEVICE_UUID, so sending it by default keeps every
     * Sprint 2–9 suite green. Device-blocking tests flush headers to send none.
     */
    protected function setUp(): void
    {
        // Sprint 21 — Blade view compilation (and cache/session drivers) require
        // Laravel's runtime storage folders to exist. On a fresh CI checkout these
        // are absent (they are git-ignored), which makes view-rendering tests fail
        // with "Please provide a valid cache path." Create them before the app
        // boots so every suite can compile views without extra CI wiring.
        $this->ensureRuntimeStoragePaths();

        parent::setUp();

        $this->withHeader('X-Device-UUID', TenantFactory::AUTO_DEVICE_UUID);
    }

    /**
     * Ensure Laravel's required runtime cache/view/session folders exist. Idempotent.
     */
    protected function ensureRuntimeStoragePaths(): void
    {
        $base = dirname(__DIR__);

        $paths = [
            $base.'/storage/framework/views',
            $base.'/storage/framework/cache',
            $base.'/storage/framework/cache/data',
            $base.'/storage/framework/sessions',
            $base.'/storage/framework/testing',
            $base.'/storage/logs',
            $base.'/bootstrap/cache',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

    /**
     * Clear the auto-provisioned subscription + devices so a test can install an
     * exact subscription/device state. Returns the freshly attached subscription
     * on the requested plan (defaults to Starter with the given device cap).
     */
    protected function resetSubscriptionState(Tenant $tenant): void
    {
        $tenant->registeredDevices()->delete();
        $tenant->tenantSubscriptions()->delete();
    }

    protected function starterPlan(int $maxDevices = 3, int $maxStores = 1): SubscriptionPlan
    {
        return SubscriptionPlan::query()->firstOrCreate(
            ['code' => SubscriptionPlan::CODE_STARTER],
            [
                'name' => 'Starter',
                'price_monthly' => 99000,
                'max_stores' => $maxStores,
                'max_devices' => $maxDevices,
                'is_active' => true,
            ],
        );
    }

    /**
     * Attach an ACTIVE subscription (given plan) to a tenant whose auto state was
     * reset. Sprint 10 helper.
     */
    protected function attachActiveSubscription(Tenant $tenant, SubscriptionPlan $plan): TenantSubscription
    {
        return TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
