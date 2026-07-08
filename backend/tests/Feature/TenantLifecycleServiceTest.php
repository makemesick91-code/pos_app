<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantLifecycleDecision;
use App\Services\TenantLifecycle\TenantLifecycleService;
use App\Services\TenantLifecycle\TenantLifecycleStatus;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 25 — TenantLifecycleService is the single source of truth and applies
 * the manual-suspension-first precedence (TLS-R001, TLS-R004).
 */
class TenantLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TenantLifecycleService
    {
        return app(TenantLifecycleService::class);
    }

    public function test_active_tenant_is_allowed(): void
    {
        $tenant = Tenant::factory()->create();

        $decision = $this->service()->resolve($tenant);

        $this->assertTrue($decision->allowed);
        $this->assertContains($decision->status, [TenantLifecycleStatus::ACTIVE, TenantLifecycleStatus::GRACE, TenantLifecycleStatus::PAST_DUE]);
        $this->assertFalse($decision->manuallySuspended);
    }

    public function test_manual_suspension_takes_precedence(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend($tenant, $admin, 'Precedence test.');

        $decision = $this->service()->resolve($tenant->refresh());

        $this->assertFalse($decision->allowed);
        $this->assertSame(TenantLifecycleStatus::SUSPENDED, $decision->status);
        $this->assertSame('TENANT_SUSPENDED', $decision->code);
        $this->assertSame(TenantLifecycleDecision::SOURCE_MANUAL_SUSPENSION, $decision->source);
    }

    public function test_legacy_suspended_tenant_status_is_blocked(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_SUSPENDED]);

        $decision = $this->service()->resolve($tenant);

        $this->assertFalse($decision->allowed);
        $this->assertSame(TenantLifecycleStatus::SUSPENDED, $decision->status);
        $this->assertSame(TenantLifecycleDecision::SOURCE_TENANT_STATUS, $decision->source);
    }

    public function test_inactive_tenant_maps_to_archived_blocked(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);

        $decision = $this->service()->resolve($tenant);

        $this->assertFalse($decision->allowed);
        $this->assertSame(TenantLifecycleStatus::ARCHIVED, $decision->status);
    }

    public function test_blocked_set_is_the_documented_set(): void
    {
        $this->assertSame(
            [TenantLifecycleStatus::SUSPENDED, TenantLifecycleStatus::CANCELLED, TenantLifecycleStatus::ARCHIVED],
            TenantLifecycleStatus::blocked(),
        );
    }
}
