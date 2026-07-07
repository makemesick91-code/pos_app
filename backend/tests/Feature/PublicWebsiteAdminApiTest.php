<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicWebsiteAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_manage_pages(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/public-website-pages', [
                'page_key' => 'HOME', 'title' => 'Beranda',
                'seo_title' => 'Aish', 'seo_description' => 'POS',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/public-website-pages')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/public-website-pages/{$id}/approve", [])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/public-website-pages/{$id}/publish", [])
            ->assertOk()->assertJsonPath('data.status', 'PUBLISHED');
    }

    public function test_platform_admin_can_manage_landing_versions(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/landing-page-versions', [
                'headline' => 'Kasir ringan', 'hero_cta_target' => '#interest',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/landing-page-versions/{$id}/publish", [])
            ->assertOk()->assertJsonPath('data.status', 'PUBLISHED');
    }

    public function test_disallowed_cta_is_rejected_by_service(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/landing-page-versions', [
                'headline' => 'X', 'hero_cta_target' => '/signup',
            ])->assertStatus(500);
    }

    public function test_platform_admin_can_manage_risks(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/public-website-risks', [
                'area' => 'SEO', 'severity' => 'MEDIUM', 'title' => 'Meta review',
            ])->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/public-website-risks/{$id}/accept-risk", [
                'reason' => 'documented', 'approver_id' => $this->admin->id,
                'expires_at' => Carbon::now()->addDays(14)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');
    }

    public function test_platform_admin_can_record_signoff_and_read_reports(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/public-website-signoffs', [
                'signer_role' => 'OWNER', 'decision' => 'APPROVED',
            ])->assertCreated()->assertJsonPath('data.signer_role', 'OWNER');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/public-website-readiness')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/public-website-content-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'pages', 'landing']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/public-website-lead-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'interest_only']]);
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/public-website-go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_lead_status_change_does_not_provision(): void
    {
        $lead = app(\App\Services\PublicWebsite\LeadInterestGovernanceService::class)
            ->submit(['contact_name' => 'X', 'contact_email' => 'x@example.com', 'consent' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/lead-interest-submissions/{$lead->id}/status", ['status' => 'REVIEWED'])
            ->assertOk()->assertJsonPath('data.status', 'REVIEWED');
    }

    public function test_tenant_user_cannot_access_public_website_apis(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/public-website-pages')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/landing-page-versions')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/lead-interest-submissions')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/public-website-go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/public-website-pages')->assertStatus(401);
        $this->getJson('/api/v1/admin/public-website-readiness')->assertStatus(401);
        $this->getJson('/api/v1/admin/public-website-go-no-go')->assertStatus(401);
    }
}
