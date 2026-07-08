<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function base(): string
    {
        return '/api/v1/admin/subscription-renewal';
    }

    private function candidateId(): int
    {
        $sub = TenantSubscription::factory()->create(['subscription_plan_id' => SubscriptionPlan::factory()->create()->id, 'ends_at' => now()->addDays(5)]);
        $run = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/runs', [])->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/runs/{$run}/evaluate", [])->assertOk();

        return (int) SubscriptionRenewalCandidate::query()->where('tenant_subscription_id', $sub->id)->firstOrFail()->id;
    }

    public function test_platform_admin_can_manage_policies(): void
    {
        $id = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/policies', ['code' => 'M1', 'name' => 'Monthly'])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/policies')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson($this->base()."/policies/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson($this->base()."/policies/{$id}", ['status' => 'INACTIVE'])
            ->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/policies/ensure-default')
            ->assertSuccessful()->assertJsonPath('data.code', 'DEFAULT_MANUAL_RENEWAL');
    }

    public function test_platform_admin_can_run_and_evaluate_candidates(): void
    {
        $candidateId = $this->candidateId();

        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/candidates')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/candidates/{$candidateId}/ready-for-manual-renewal")
            ->assertOk()->assertJsonPath('data.status', 'READY_FOR_MANUAL_RENEWAL');
    }

    public function test_platform_admin_can_manage_dunning_notices(): void
    {
        $candidateId = $this->candidateId();

        $noticeId = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/candidates/{$candidateId}/dunning-notices", [
                'notice_type' => 'RENEWAL_REMINDER', 'channel' => 'WHATSAPP_MANUAL', 'summary' => 'Reminder',
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/dunning-notices/{$noticeId}/mark-sent-manually")
            ->assertOk()->assertJsonPath('data.status', 'MARKED_SENT_MANUALLY');
    }

    public function test_platform_admin_can_record_and_apply_decision(): void
    {
        $candidateId = $this->candidateId();

        $decisionId = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/candidates/{$candidateId}/decisions", [
                'decision' => 'APPROVE_MANUAL_RENEWAL',
                'decided_by_user_id' => $this->admin->id,
                'effective_start_date' => now()->toDateString(),
                'effective_end_date' => now()->addMonth()->toDateString(),
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/decisions/{$decisionId}/apply-manual-renewal")
            ->assertOk()->assertJsonPath('data.status', 'APPLIED_MANUALLY');
    }

    public function test_platform_admin_can_manage_activities_risks_signoffs(): void
    {
        $activityId = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/activities', ['activity_type' => 'NOTE', 'summary' => 'note'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/activities/{$activityId}/complete")->assertOk()->assertJsonPath('data.status', 'DONE');

        $riskId = $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/risks', ['area' => 'PAYMENT_DELAY', 'severity' => 'MEDIUM', 'title' => 'r'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base()."/risks/{$riskId}/accept-risk", [
                'reason' => 'ok', 'approver_id' => $this->admin->id, 'expires_at' => now()->addDays(10)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/signoffs', ['signer_role' => 'FINANCE', 'decision' => 'APPROVED'])
            ->assertCreated()->assertJsonPath('data.signer_role', 'FINANCE');
    }

    public function test_platform_admin_can_read_reports(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/readiness')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/candidate-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'total_candidates']]);
        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/dunning-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'manual_only']]);
        $this->actingAs($this->admin, 'sanctum')->getJson($this->base().'/go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_mutations_are_audit_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson($this->base().'/policies', ['code' => 'AUD', 'name' => 'Audited'])->assertCreated();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_RENEWAL_POLICY_CREATED,
            'target_type' => AdminAuditLog::TARGET_SUBSCRIPTION_RENEWAL_POLICY,
        ]);
    }

    public function test_tenant_user_cannot_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson($this->base().'/policies')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson($this->base().'/go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson($this->base().'/policies')->assertStatus(401);
        $this->getJson($this->base().'/readiness')->assertStatus(401);
        $this->getJson($this->base().'/go-no-go')->assertStatus(401);
    }
}
