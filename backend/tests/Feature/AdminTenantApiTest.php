<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — admin tenant list/detail. Platform admin sees cross-tenant
 * summaries with subscription + device + store counts. No secrets leak.
 */
class AdminTenantApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_list_tenants(): void
    {
        Tenant::factory()->create(['name' => 'Alpha Store']);
        Tenant::factory()->create(['name' => 'Beta Store']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'status', 'stores_count', 'devices_active_count', 'subscription']],
                'meta' => ['foundation'],
            ]);
    }

    public function test_platform_admin_can_show_tenant_detail_with_subscription_and_device_summary(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Gamma']);
        Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.subscription.status', 'ACTIVE')
            ->assertJsonPath('data.devices.active_count', 1)
            ->assertJsonPath('data.devices.max_devices', 3)
            ->assertJsonStructure(['data' => ['stores', 'subscription' => ['plan_code', 'ends_at']]]);
    }

    public function test_tenant_list_filter_by_query_matches_name(): void
    {
        Tenant::factory()->create(['name' => 'Unique Coffee House']);
        Tenant::factory()->create(['name' => 'Other Business']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/tenants?q=Unique')
            ->assertOk();

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Unique Coffee House', $names);
        $this->assertNotContains('Other Business', $names);
    }

    public function test_tenant_detail_does_not_expose_secrets_or_raw_payment_payloads(): void
    {
        $tenant = Tenant::factory()->create();

        $raw = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->getContent();

        foreach (['password', 'server_key', 'secret', 'api_key', 'gateway_payload', 'signature'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $raw);
        }
    }
}
