<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-6 — the Platform Admin and Tenant Owner support/observability/incident
 * surfaces never share authorization. An owner session cannot reach the admin
 * support/observability/incident console, a platform-admin session cannot reach
 * the owner support view, and an API bearer token never authenticates a browser
 * console (UIX6-R003/R004/R005/R006).
 */
class Uix6SupportSurfaceSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->tenantOwner()->create(['tenant_id' => $tenant->id]);
    }

    public function test_owner_session_cannot_access_admin_support_surfaces(): void
    {
        $owner = $this->owner();

        foreach (['/admin/support', '/admin/support/tenants', '/admin/observability', '/admin/incidents'] as $path) {
            $this->actingAs($owner, 'owner')->get($path)->assertRedirect('/admin/login');
        }
    }

    public function test_admin_session_cannot_access_owner_support(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')->get('/owner/support')->assertRedirect('/owner/login');
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $this->get('/admin/support')->assertRedirect('/admin/login');
        $this->get('/admin/observability')->assertRedirect('/admin/login');
        $this->get('/admin/incidents')->assertRedirect('/admin/login');
        $this->get('/owner/support')->assertRedirect('/owner/login');
    }

    public function test_api_bearer_token_does_not_authenticate_the_browser_consoles(): void
    {
        $owner = $this->owner();
        $ownerToken = $owner->createToken('device')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$ownerToken, 'Accept' => 'text/html'])
            ->get('/owner/support')->assertRedirect('/owner/login');

        $admin = User::factory()->platformAdmin()->create();
        $adminToken = $admin->createToken('device')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken, 'Accept' => 'text/html'])
            ->get('/admin/support')->assertRedirect('/admin/login');
    }
}
