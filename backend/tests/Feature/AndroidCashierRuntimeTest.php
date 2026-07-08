<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantManualSuspension;
use App\Models\User;
use App\Services\AndroidRuntime\AndroidRuntimeDecision;
use App\Services\AndroidRuntime\CashierRuntimeSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sprint 34 — cashier runtime session validation (ADR-R010/R011).
 */
class AndroidCashierRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['code' => 'ADR-CASH']);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function service(): CashierRuntimeSessionService
    {
        return app(CashierRuntimeSessionService::class);
    }

    private function cashier(string $role = User::ROLE_CASHIER, ?int $tenantId = null): User
    {
        return User::factory()->create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'store_id' => $this->store->id,
            'role' => $role,
        ]);
    }

    public function test_valid_cashier_session_is_allowed(): void
    {
        $decision = $this->service()->check($this->tenant, $this->cashier());

        $this->assertTrue($decision->allowed);
        $this->assertSame(AndroidRuntimeDecision::STATUS_ALLOWED, $decision->status);
    }

    public function test_non_operator_role_is_denied(): void
    {
        $decision = $this->service()->check($this->tenant, $this->cashier('viewer'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('CASHIER_ROLE_INVALID', $decision->reasonCode);
    }

    public function test_wrong_tenant_is_denied(): void
    {
        $other = Tenant::factory()->create(['code' => 'OTHER']);
        $foreign = $this->cashier(User::ROLE_CASHIER, $other->id);

        $decision = $this->service()->check($this->tenant, $foreign);

        $this->assertFalse($decision->allowed);
        $this->assertSame('CASHIER_TENANT_MISMATCH', $decision->reasonCode);
    }

    public function test_manual_suspension_wins_over_paid_state(): void
    {
        TenantManualSuspension::query()->create([
            'tenant_id' => $this->tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'hold',
            'effective_at' => Carbon::now(),
        ]);

        $decision = $this->service()->check($this->tenant, $this->cashier());

        $this->assertFalse($decision->allowed);
        $this->assertSame('MANUALLY_SUSPENDED', $decision->reasonCode);
        $this->assertSame('tenant_suspended', $decision->conflictCode);

        // Denied cashier decision is audit-logged (ADR-R011).
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'ANDROID_CASHIER_DENIED',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
