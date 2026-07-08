<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\LeadInterestSubmission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesPipelineAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function submission(): LeadInterestSubmission
    {
        return LeadInterestSubmission::query()->create([
            'lead_reference' => 'IL-'.uniqid(),
            'status' => LeadInterestSubmission::STATUS_NEW,
            'business_name' => 'Toko Maju',
            'contact_email' => 'maju@example.com',
            'source' => 'public-website',
            'consent_accepted_at' => Carbon::now(),
        ]);
    }

    public function test_platform_admin_can_manage_stages(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-pipeline/stages/ensure-defaults', [])
            ->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/sales-pipeline/stages')
            ->assertOk()->assertJsonPath('data.0.stage_code', 'NEW');

        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-pipeline/stages', ['stage_code' => 'PILOT_REVIEW', 'name' => 'Pilot Review'])
            ->assertCreated();
        $id = $create->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/sales-pipeline/stages/{$id}", ['name' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.name', 'Renamed');
    }

    public function test_platform_admin_can_manage_leads_and_import_interest(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-leads', ['business_name' => 'Warung', 'contact_email' => 'w@example.com'])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sales-leads')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/sales-leads/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/sales-leads/{$id}", ['priority' => 'HIGH'])
            ->assertOk()->assertJsonPath('data.priority', 'HIGH');

        $submission = $this->submission();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/sales-leads/import-interest/{$submission->id}", [])
            ->assertCreated()->assertJsonPath('data.lead_interest_submission_id', $submission->id);
    }

    public function test_platform_admin_can_run_lead_lifecycle_without_provisioning(): void
    {
        $usersBefore = User::query()->count();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-pipeline/stages/ensure-defaults', [])->assertOk();
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-leads', ['business_name' => 'Lengkap', 'contact_email' => 'l@example.com', 'estimated_store_count' => 2, 'estimated_device_count' => 2, 'interest_package_code' => 'PRO', 'contact_name' => 'Budi', 'business_type' => 'retail'])
            ->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/sales-leads/{$create}/transition", ['stage_code' => 'CONTACTED'])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/sales-leads/{$create}/qualify", [])->assertOk()->assertJsonPath('data.status', 'QUALIFIED');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/sales-leads/{$create}/ready-for-onboarding", [])->assertOk()->assertJsonPath('data.status', 'WON_READY_FOR_ONBOARDING');

        $this->assertSame(0, Tenant::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
    }

    public function test_platform_admin_can_add_activities_and_assign(): void
    {
        $leadId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-leads', ['business_name' => 'X'])->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/sales-leads/{$leadId}/activities", ['activity_type' => 'WHATSAPP_MANUAL', 'summary' => 'WA manual'])
            ->assertCreated()->assertJsonPath('data.activity_type', 'WHATSAPP_MANUAL');
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/sales-leads/{$leadId}/activities")->assertOk();

        $owner = User::factory()->create();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/sales-leads/{$leadId}/assign", ['assigned_to_user_id' => $owner->id])
            ->assertCreated();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/sales-leads/{$leadId}/unassign", [])
            ->assertOk()->assertJsonPath('data.assigned_to_user_id', null);
    }

    public function test_platform_admin_can_manage_risks_and_signoffs(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-pipeline/risks', ['area' => 'LEAD_QUALITY', 'severity' => 'MEDIUM', 'title' => 'review'])
            ->assertCreated();
        $id = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/sales-pipeline/risks/{$id}/accept-risk", [
                'reason' => 'documented', 'approver_id' => $this->admin->id,
                'expires_at' => Carbon::now()->addDays(14)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-pipeline/signoffs', ['signer_role' => 'OWNER', 'decision' => 'APPROVED'])
            ->assertCreated()->assertJsonPath('data.signer_role', 'OWNER');
    }

    public function test_platform_admin_can_read_reports(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/readiness')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/lead-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'total_leads']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/activity-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'manual_follow_up_only']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_mutations_are_audit_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/sales-leads', ['business_name' => 'Audited'])->assertCreated();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_SALES_LEAD_CREATED,
            'target_type' => AdminAuditLog::TARGET_SALES_LEAD,
        ]);
    }

    public function test_tenant_user_cannot_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/sales-leads')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/stages')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/sales-pipeline/go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/sales-leads')->assertStatus(401);
        $this->getJson('/api/v1/admin/sales-pipeline/readiness')->assertStatus(401);
        $this->getJson('/api/v1/admin/sales-pipeline/go-no-go')->assertStatus(401);
    }
}
