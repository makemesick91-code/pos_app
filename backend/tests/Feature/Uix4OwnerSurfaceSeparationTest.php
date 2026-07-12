<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — the Owner Web Console is a distinct surface. A platform-admin session
 * can never reach it and an owner session can never reach the platform-admin
 * console (UIX4-R002/R003). Separation is enforced at the session-guard layer,
 * not by hiding navigation.
 */
class Uix4OwnerSurfaceSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->tenantOwner()->create([
            'tenant_id' => Tenant::factory()->create()->id,
        ]);
    }

    public function test_owner_session_cannot_reach_platform_admin_console(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_platform_admin_session_cannot_reach_owner_console(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')
            ->get('/owner')
            ->assertRedirect(route('owner.login'));
    }

    public function test_admin_web_session_does_not_authenticate_owner_guard(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // A live platform-admin session on the `web` guard is not an owner.
        $this->actingAs($admin, 'web')
            ->get('/owner/outlets')
            ->assertRedirect(route('owner.login'));
    }

    public function test_owner_guard_session_does_not_authenticate_admin_web(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->get('/admin/tenants')
            ->assertRedirect(route('admin.login'));
    }

    public function test_sanctum_token_alone_does_not_authenticate_owner_web(): void
    {
        $owner = $this->owner();

        // Authenticated on the API (sanctum) guard, but the owner web console
        // uses its own session guard — the API identity does not carry over.
        $this->actingAs($owner, 'sanctum')
            ->get('/owner')
            ->assertRedirect(route('owner.login'));
    }

    public function test_public_visitor_cannot_reach_either_console(): void
    {
        $this->get('/owner')->assertRedirect(route('owner.login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
