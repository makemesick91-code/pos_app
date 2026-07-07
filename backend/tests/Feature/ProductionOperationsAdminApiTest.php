<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionOperationsAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_manage_operation_runs(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/production-operation-runs', [])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/production-operation-runs')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/production-operation-runs/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-operation-runs/{$id}/approve", [])
            ->assertOk();
    }

    public function test_platform_admin_can_manage_incidents(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/production-incidents', [
                'title' => 'API down', 'area' => 'BACKEND_API', 'severity' => 'P2', 'impact' => 'DEGRADED',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/production-incidents')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-incidents/{$id}/status", ['status' => 'INVESTIGATING'])
            ->assertOk()->assertJsonPath('data.status', 'INVESTIGATING');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-incidents/{$id}/accept-risk", [
                'reason' => 'documented workaround',
                'approver_id' => $this->admin->id,
                'expires_at' => Carbon::now()->addDays(7)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');
    }

    public function test_platform_admin_can_manage_maintenance_windows(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/production-maintenance-windows', [
                'title' => 'DB migration',
                'scheduled_start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'scheduled_end_at' => Carbon::now()->addDay()->addHours(2)->toDateTimeString(),
                'risk_level' => 'LOW',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-maintenance-windows/{$id}/status", ['status' => 'APPROVED'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_read_only_endpoints_return_decisions(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/production-ops-health')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/production-incident-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'counts']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/production-post-handover-go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_tenant_user_cannot_access_operations_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-operation-runs')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-incidents')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-maintenance-windows')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-post-handover-go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/production-operation-runs')->assertStatus(401);
        $this->getJson('/api/v1/admin/production-incidents')->assertStatus(401);
        $this->getJson('/api/v1/admin/production-ops-health')->assertStatus(401);
    }
}
