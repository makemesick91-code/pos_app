<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Handover\ProductionHandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHandoverAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_manage_closures(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/pilot-closures', [])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/pilot-closures')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/pilot-closures/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/pilot-closures/{$id}/approve", [])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_platform_admin_can_manage_handovers_and_signoffs(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/production-handovers', ['candidate_tag' => 'sprint-18-go'])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/production-handovers')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/production-handovers/{$id}", ['candidate_commit' => '773f017'])
            ->assertOk()->assertJsonPath('data.candidate_commit', '773f017');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-handovers/{$id}/mark-ready")
            ->assertOk()->assertJsonPath('data.status', 'READY');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/production-handovers/{$id}/signoffs", [
                'signer_role' => 'OWNER', 'decision' => 'APPROVED',
            ])->assertCreated()->assertJsonPath('data.signer_role', 'OWNER');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/production-handovers/{$id}/signoffs")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_go_no_go_endpoint_returns_decision(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/production-handover-go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_tenant_user_cannot_access_closure_handover_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/pilot-closures')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-handovers')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/production-handover-go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/pilot-closures')->assertStatus(401);
        $this->getJson('/api/v1/admin/production-handovers')->assertStatus(401);
    }

    public function test_resources_do_not_expose_app_secrets(): void
    {
        $package = app(ProductionHandoverService::class)->create([
            'candidate_commit' => '773f017',
            'candidate_tag' => 'sprint-18-go',
        ], $this->admin);

        $raw = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/production-handovers/{$package->id}")->getContent();

        // The application key / gateway secrets must never appear in a resource.
        $this->assertStringNotContainsString((string) config('app.key'), $raw);
        $this->assertStringNotContainsString('sk_live', $raw);
        $this->assertStringNotContainsString('MIDTRANS_SERVER_KEY', $raw);
    }
}
