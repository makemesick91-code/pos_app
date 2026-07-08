<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCollectionAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    private function accountId(): int
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/accounts', ['billing_name' => 'Acct', 'payment_terms_days' => 7])
            ->assertCreated()->json('data.id');
    }

    private function issuedInvoiceId(int $accountId, float $amount = 100000): int
    {
        $invoiceId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/invoices', ['billing_account_id' => $accountId])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/invoices/{$invoiceId}/lines", [
                'item_type' => 'SUBSCRIPTION', 'description' => 'Monthly', 'quantity' => 1, 'unit_amount' => $amount,
            ])->assertCreated();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/invoices/{$invoiceId}/issue", [])
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');

        return $invoiceId;
    }

    public function test_platform_admin_can_manage_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $id = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/accounts', ['billing_name' => 'Toko', 'tenant_id' => $tenant->id])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/accounts')->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/billing/accounts/{$id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/billing/accounts/{$id}", ['status' => 'ON_HOLD'])
            ->assertOk()->assertJsonPath('data.status', 'ON_HOLD');
    }

    public function test_platform_admin_can_manage_cycles(): void
    {
        $id = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/cycles', ['period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/billing/cycles/{$id}/open")->assertOk()->assertJsonPath('data.status', 'OPEN');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/billing/cycles/{$id}/lock")->assertOk()->assertJsonPath('data.status', 'LOCKED');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/admin/billing/cycles/{$id}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_platform_admin_can_manage_invoices_and_lines(): void
    {
        $accountId = $this->accountId();
        $invoiceId = $this->issuedInvoiceId($accountId, 200000);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/invoices')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/billing/invoices/{$invoiceId}")
            ->assertOk()->assertJsonPath('data.total_amount', '200000.00');
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/invoices/{$invoiceId}/mark-overdue")
            ->assertOk()->assertJsonPath('data.status', 'OVERDUE');
    }

    public function test_platform_admin_can_void_invoice(): void
    {
        $accountId = $this->accountId();
        $invoiceId = $this->issuedInvoiceId($accountId);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/invoices/{$invoiceId}/void", ['void_reason' => 'mistake'])
            ->assertOk()->assertJsonPath('data.status', 'VOIDED');
    }

    public function test_platform_admin_can_submit_and_review_payment_evidence(): void
    {
        $accountId = $this->accountId();
        $invoiceId = $this->issuedInvoiceId($accountId, 100000);

        $evidenceId = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/invoices/{$invoiceId}/payment-evidences", [
                'payment_method' => 'BANK_TRANSFER', 'amount' => 100000,
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/v1/admin/billing/invoices/{$invoiceId}/payment-evidences")->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/payment-evidences/{$evidenceId}/accept")
            ->assertOk()->assertJsonPath('data.status', 'ACCEPTED');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/billing/invoices/{$invoiceId}")
            ->assertOk()->assertJsonPath('data.status', 'PAID');
    }

    public function test_platform_admin_can_manage_activities(): void
    {
        $activityId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/activities', ['activity_type' => 'WHATSAPP_MANUAL', 'summary' => 'WA manual'])
            ->assertCreated()->assertJsonPath('data.activity_type', 'WHATSAPP_MANUAL')->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/activities')->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/activities/{$activityId}/complete")
            ->assertOk()->assertJsonPath('data.status', 'DONE');
    }

    public function test_platform_admin_can_manage_risks_and_signoffs(): void
    {
        $riskId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/risks', ['area' => 'PAYMENT_DELAY', 'severity' => 'MEDIUM', 'title' => 'review'])
            ->assertCreated()->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/billing/risks/{$riskId}/accept-risk", [
                'reason' => 'documented', 'approver_id' => $this->admin->id,
                'expires_at' => now()->addDays(14)->toDateString(),
            ])->assertOk()->assertJsonPath('data.status', 'ACCEPTED_RISK');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/billing/signoffs', ['signer_role' => 'FINANCE', 'decision' => 'APPROVED'])
            ->assertCreated()->assertJsonPath('data.signer_role', 'FINANCE');
    }

    public function test_platform_admin_can_read_reports(): void
    {
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/readiness')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/invoice-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'total_invoices']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/collection-summary')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'manual_follow_up_only']]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/billing/go-no-go')
            ->assertOk()->assertJsonStructure(['data' => ['decision', 'signals', 'gates']]);
    }

    public function test_mutations_are_audit_logged(): void
    {
        $this->accountId();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_BILLING_ACCOUNT_CREATED,
            'target_type' => AdminAuditLog::TARGET_SAAS_BILLING_ACCOUNT,
        ]);
    }

    public function test_tenant_user_cannot_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/billing/accounts')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/billing/go-no-go')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $this->getJson('/api/v1/admin/billing/accounts')->assertStatus(401);
        $this->getJson('/api/v1/admin/billing/readiness')->assertStatus(401);
        $this->getJson('/api/v1/admin/billing/go-no-go')->assertStatus(401);
    }
}
