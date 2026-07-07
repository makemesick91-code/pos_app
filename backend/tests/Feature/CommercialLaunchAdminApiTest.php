<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommercialLaunchAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_manage_launch_runs_and_signoffs(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/commercial-launch-runs', [])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/commercial-launch-runs')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/commercial-launch-runs/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/commercial-launch-runs/{$id}/approve", [])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/commercial-launch-runs/{$id}/signoffs", [
                'signer_role' => 'OWNER', 'decision' => 'APPROVED',
            ])->assertCreated()->assertJsonPath('data.signer_role', 'OWNER');
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/commercial-launch-runs/{$id}/signoffs")->assertOk();
    }

    public function test_platform_admin_can_manage_packages(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/saas-packages', [
                'name' => 'UMKM Starter', 'target_segment' => 'GENERAL_UMKM',
                'monthly_price' => 99000, 'device_limit' => 2,
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/saas-packages')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/saas-packages/{$id}/approve", [])
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/saas-packages/{$id}/retire", [])
            ->assertOk()->assertJsonPath('data.status', 'RETIRED');
    }

    public function test_platform_admin_can_manage_risks(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/commercial-risks', [
                'area' => 'PRICING', 'severity' => 'MEDIUM', 'title' => 'Pricing review',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/commercial-risks')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/commercial-risks/{$id}/accept-risk", [
                'reason' => 'documented',
                'approver_id' => $this->admin->id,
                'expires_at' => Carbon::now()->addDays(14)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');
    }

    public function test_read_only_endpoints_return_decisions(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/commercial-launch-readiness')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/commercial-package-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'counts']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/commercial-onboarding-capacity')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'capacity_per_week']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/commercial-launch-go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_tenant_user_cannot_access_commercial_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/commercial-launch-runs')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/saas-packages')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/commercial-risks')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/commercial-launch-go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/commercial-launch-runs')->assertStatus(401);
        $this->getJson('/api/v1/admin/saas-packages')->assertStatus(401);
        $this->getJson('/api/v1/admin/commercial-launch-readiness')->assertStatus(401);
    }
}
