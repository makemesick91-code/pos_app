<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 20 — regression guard. The cumulative Sprint 13–19 gate commands must
 * remain registered and prior admin routes must still be protected, so the
 * commercial launch layer never silently drops a prior sprint's contract.
 */
class CommercialLaunchRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_prior_sprint_gate_commands_still_registered(): void
    {
        $registered = array_keys(Artisan::all());
        foreach ((array) config('commercial_launch.required_commands', []) as $command) {
            $this->assertContains($command, $registered, "Missing prior command {$command}");
        }
    }

    public function test_prior_admin_routes_still_require_auth(): void
    {
        $this->getJson('/api/v1/admin/production-post-handover-go-no-go')->assertStatus(401);
        $this->getJson('/api/v1/admin/production-operation-runs')->assertStatus(401);
        $this->getJson('/api/v1/admin/tenants')->assertStatus(401);
    }

    public function test_commercial_routes_registered_and_protected(): void
    {
        $this->getJson('/api/v1/admin/commercial-launch-go-no-go')->assertStatus(401);
        $this->getJson('/api/v1/admin/saas-packages')->assertStatus(401);
    }
}
