<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 21 — regression: prior-sprint admin surfaces remain intact and the new
 * public website surface does not disturb existing behavior.
 */
class PublicWebsiteRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_prior_admin_and_commercial_routes_still_respond(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // Sprint 11 admin, Sprint 20 commercial — still reachable for platform admin.
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/tenants')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/commercial-launch-go-no-go')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/commercial-package-summary')->assertOk();
    }

    public function test_prior_sprint_gate_commands_remain_registered(): void
    {
        foreach ((array) config('public_website.required_commands', []) as $command) {
            $this->assertArrayHasKey($command, \Illuminate\Support\Facades\Artisan::all(), "Missing command: {$command}");
        }
    }

    public function test_commercial_launch_go_no_go_command_still_runs(): void
    {
        // NO_GO on fresh DB is correct; the contract is that it still runs cleanly.
        $this->artisan('commercial:launch-go-no-go --json')->assertExitCode(1);
    }
}
