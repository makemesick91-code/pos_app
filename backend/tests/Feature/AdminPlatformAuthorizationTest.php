<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — the platform.admin middleware guards every /api/v1/admin route.
 * Platform admins are allowed; tenant business users are forbidden with a stable
 * payload; unauthenticated requests are rejected.
 */
class AdminPlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonPath('meta.foundation', 'POS_ANDROID_SAAS_FOUNDATION');
    }

    public function test_tenant_user_cannot_access_admin_endpoints(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }

    public function test_saas_admin_role_alone_is_not_a_platform_admin(): void
    {
        // A user with the legacy saas_admin role but without the platform-admin
        // flag must not reach admin SaaS APIs.
        $user = User::factory()->saasAdmin()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }

    public function test_unauthenticated_user_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/tenants')
            ->assertStatus(401);
    }

    public function test_inactive_platform_admin_is_blocked(): void
    {
        $admin = User::factory()->platformAdmin()->create(['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }
}
