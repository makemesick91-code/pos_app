<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\AndroidRuntime\DeviceActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 35 — support-ops:* command coverage.
 */
class Sprint35SupportCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'SUP-CMD']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
        User::factory()->platformAdmin()->create();
    }

    public function test_read_commands_run(): void
    {
        foreach ([
            'support-ops:tenant-health',
            'support-ops:timeline',
            'support-ops:billing-status',
            'support-ops:payment-status',
            'support-ops:entitlement-denials',
            'support-ops:sync-failures',
            'support-ops:incident-summary',
        ] as $command) {
            $this->assertSame(0, Artisan::call($command, ['--tenant' => (string) $this->tenant->id, '--json' => true]), $command);
        }
    }

    public function test_device_action_dry_run_does_not_mutate(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'cmd-token-1', 'cmd-fp-1', 'cmd-dev-1', 'Kasir', $this->owner);

        $this->assertSame(0, Artisan::call('support-ops:device-action', [
            '--activation' => (string) $activation->id,
            '--action' => 'revoke',
            '--json' => true,
        ]));

        $this->assertSame(TenantDeviceActivation::STATUS_ACTIVATED, $activation->fresh()->activation_status);
    }

    public function test_device_action_execute_revokes_via_service(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'cmd-token-2', 'cmd-fp-2', 'cmd-dev-2', 'Kasir', $this->owner);

        $exit = Artisan::call('support-ops:device-action', [
            '--activation' => (string) $activation->id,
            '--action' => 'revoke',
            '--reason' => 'device_lost_or_stolen',
            '--execute' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(TenantDeviceActivation::STATUS_REVOKED, $activation->fresh()->activation_status);
        $this->assertDatabaseHas('tenant_support_actions', ['action_type' => 'device_revoked', 'status' => 'completed']);
    }

    public function test_device_action_output_has_no_raw_token(): void
    {
        $activation = app(DeviceActivationService::class)
            ->activate($this->tenant, 'cmd-token-3', 'cmd-fp-3', 'cmd-dev-3', 'Kasir', $this->owner);

        Artisan::call('support-ops:device-action', [
            '--activation' => (string) $activation->id,
            '--action' => 'revoke',
            '--reason' => 'device_decommission',
            '--execute' => true,
            '--json' => true,
        ]);
        $output = Artisan::output();
        $this->assertStringNotContainsString('cmd-token-3', $output);
        $this->assertDoesNotMatchRegularExpression('/password|secret|private_key/i', $output);
    }
}
