<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-3 — platform-admin browser login + authorization boundary.
 */
class Uix3AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_ERROR = 'The provided credentials are incorrect.';

    private function admin(array $attrs = []): User
    {
        return User::factory()->platformAdmin()->create($attrs);
    }

    public function test_login_page_loads(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Masuk Platform Admin')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_platform_admin_can_login(): void
    {
        $admin = $this->admin(['email' => 'ops@aish.test']);

        $this->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_successful_login_is_audited_without_password(): void
    {
        $this->admin(['email' => 'ops@aish.test']);

        $this->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'password']);

        $log = AdminAuditLog::query()->where('action', AdminAuditLog::ACTION_ADMIN_LOGIN)->first();
        $this->assertNotNull($log);
        $encoded = json_encode($log->getAttributes());
        $this->assertStringNotContainsStringIgnoringCase('password', (string) $encoded);
    }

    public function test_wrong_password_is_rejected_generically(): void
    {
        $this->admin(['email' => 'ops@aish.test']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'nope'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('web');
    }

    public function test_unknown_email_is_rejected_with_same_generic_message(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'ghost@aish.test', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('web');
    }

    public function test_tenant_user_cannot_login_to_console(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        User::factory()->tenantOwner()->create([
            'email' => 'owner@tenant.test',
            'tenant_id' => $tenant->id,
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'owner@tenant.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        // A tenant user must never obtain a console session.
        $this->assertGuest('web');
    }

    public function test_deactivated_platform_admin_cannot_login(): void
    {
        $this->admin(['email' => 'ops@aish.test', 'is_active' => false]);

        $this->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->admin(['email' => 'ops@aish.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'wrong']);
        }

        $response = $this->post('/admin/login', ['email' => 'ops@aish.test', 'password' => 'password']);
        $response->assertSessionHasErrors('email');

        // Even the correct password is blocked while locked out.
        $this->assertGuest('web');
        $errors = session('errors')->get('email');
        $this->assertStringContainsStringIgnoringCase('too many', implode(' ', $errors));
    }

    public function test_no_open_redirect_via_request_field(): void
    {
        $this->admin(['email' => 'ops@aish.test']);

        $this->post('/admin/login', [
            'email' => 'ops@aish.test',
            'password' => 'password',
            'redirect' => 'https://evil.example.com',
            'intended' => 'https://evil.example.com',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_admin_is_redirected_away_from_login(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout_invalidates_session(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('web');
    }

    public function test_logout_must_be_post_not_get(): void
    {
        $this->get('/admin/logout')->assertStatus(405);
    }

    // ---- Authorization boundary ----

    public function test_guest_cannot_reach_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_guest_cannot_reach_tenant_list_or_detail(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get('/admin/tenants')->assertRedirect(route('admin.login'));
        $this->get("/admin/tenants/{$tenant->id}")->assertRedirect(route('admin.login'));
    }

    public function test_tenant_user_session_cannot_reach_console(): void
    {
        $owner = User::factory()->tenantOwner()->create([
            'tenant_id' => Tenant::factory()->create()->id,
        ]);

        $this->actingAs($owner, 'web')
            ->get('/admin')
            ->assertRedirect(route('admin.login'));

        // Defence in depth logs the intruding session out.
        $this->assertGuest('web');
    }

    public function test_platform_admin_can_reach_console(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'web')->get('/admin')->assertOk();
        $this->actingAs($admin, 'web')->get('/admin/tenants')->assertOk();
    }

    public function test_deactivated_admin_session_is_denied(): void
    {
        $admin = $this->admin(['is_active' => false]);

        $this->actingAs($admin, 'web')
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }
}
