<?php

namespace Tests\Feature;

use App\Models\PilotDefect;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotDefectAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function seedDefect(array $overrides = []): PilotDefect
    {
        return app(PilotDefectService::class)->create(array_merge([
            'title' => 'Seed defect',
            'area' => 'CASHIER',
            'severity' => 'MAJOR',
        ], $overrides), $this->admin);
    }

    public function test_platform_admin_can_list_create_show_update(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/pilot-defects', [
            'title' => 'QRIS status stuck',
            'area' => 'PAYMENT_QRIS',
            'severity' => 'CRITICAL',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->assertTrue($create->json('data.blocking'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/pilot-defects')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/pilot-defects/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/v1/admin/pilot-defects/{$id}", [
            'severity' => 'MAJOR',
        ])->assertOk()->assertJsonPath('data.severity', 'MAJOR');
    }

    public function test_tenant_user_cannot_access_admin_defect_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/pilot-defects')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/pilot-defects', [
            'title' => 'x', 'area' => 'OTHER', 'severity' => 'MINOR',
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/pilot-defects')->assertStatus(401);
    }

    public function test_lifecycle_endpoints_work_and_events_are_append_only(): void
    {
        $defect = $this->seedDefect();
        $assignee = User::factory()->platformAdmin()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-defects/{$defect->id}/assign", ['assigned_to' => $assignee->id])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-defects/{$defect->id}/status", ['status' => 'IN_PROGRESS'])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-defects/{$defect->id}/mark-fixed", [])
            ->assertOk()->assertJsonPath('data.status', 'FIXED');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-defects/{$defect->id}/verify", ['passed' => true])
            ->assertOk()->assertJsonPath('data.status', 'VERIFIED');

        $events = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/pilot-defects/{$defect->id}/events")
            ->assertOk()->json('data');

        $this->assertGreaterThanOrEqual(5, count($events));
    }

    public function test_accept_risk_endpoint_preserves_severity(): void
    {
        $defect = $this->seedDefect(['severity' => 'CRITICAL']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-defects/{$defect->id}/accept-risk", [
                'reason' => 'documented workaround',
                'expires_at' => now()->addDays(7)->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACCEPTED_RISK')
            ->assertJsonPath('data.severity', 'CRITICAL');
    }

    public function test_burndown_and_stabilization_report_endpoints(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/pilot-defect-burndown')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'counts']]);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/pilot-stabilization-report')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_resources_do_not_expose_secrets(): void
    {
        $defect = $this->seedDefect([
            'metadata' => ['api_key' => 'sk_live_secret'],
            'description' => 'token=abcdef leaked',
        ]);

        $raw = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/pilot-defects/{$defect->id}")->getContent();

        $this->assertStringNotContainsString('sk_live_secret', $raw);
        $this->assertStringNotContainsString('abcdef', $raw);
    }
}
