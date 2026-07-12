<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — tenant-owner browser login + authorization boundary.
 */
class Uix4OwnerAuthTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_ERROR = 'The provided credentials are incorrect.';

    private function owner(array $attrs = []): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->tenantOwner()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attrs));
    }

    public function test_login_page_loads(): void
    {
        $this->get('/owner/login')
            ->assertOk()
            ->assertSee('Masuk Pemilik Bisnis')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_tenant_owner_can_login(): void
    {
        $owner = $this->owner(['email' => 'owner@aish.test']);

        $this->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($owner, 'owner');
        $this->assertNotNull($owner->fresh()->last_login_at);
    }

    public function test_successful_login_is_audited_without_password(): void
    {
        $this->owner(['email' => 'owner@aish.test']);

        $this->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'password']);

        $log = AdminAuditLog::query()->where('action', AdminAuditLog::ACTION_OWNER_LOGIN)->first();
        $this->assertNotNull($log);
        $encoded = json_encode($log->getAttributes());
        $this->assertStringNotContainsStringIgnoringCase('password', (string) $encoded);
    }

    public function test_wrong_password_is_rejected_generically(): void
    {
        $this->owner(['email' => 'owner@aish.test']);

        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'nope'])
            ->assertRedirect('/owner/login')
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('owner');
    }

    public function test_unknown_email_is_rejected_with_same_generic_message(): void
    {
        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'ghost@aish.test', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('owner');
    }

    public function test_cashier_cannot_login_to_owner_console(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->cashier()->create([
            'email' => 'cashier@tenant.test',
            'tenant_id' => $tenant->id,
        ]);

        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'cashier@tenant.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('owner');
    }

    public function test_platform_admin_cannot_login_to_owner_console(): void
    {
        User::factory()->platformAdmin()->create(['email' => 'ops@aish.test']);

        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'ops@aish.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('owner');
    }

    public function test_owner_without_tenant_cannot_login(): void
    {
        User::factory()->tenantOwner()->create([
            'email' => 'orphan@aish.test',
            'tenant_id' => null,
        ]);

        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'orphan@aish.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => self::GENERIC_ERROR]);

        $this->assertGuest('owner');
    }

    public function test_deactivated_owner_cannot_login(): void
    {
        $this->owner(['email' => 'owner@aish.test', 'is_active' => false]);

        $this->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('owner');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->owner(['email' => 'owner@aish.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'wrong']);
        }

        $response = $this->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'password']);
        $response->assertSessionHasErrors('email');

        $this->assertGuest('owner');
        $errors = session('errors')->get('email');
        $this->assertStringContainsStringIgnoringCase('too many', implode(' ', $errors));
    }

    public function test_no_open_redirect_via_request_field(): void
    {
        $this->owner(['email' => 'owner@aish.test']);

        $this->post('/owner/login', [
            'email' => 'owner@aish.test',
            'password' => 'password',
            'redirect' => 'https://evil.example.com',
            'intended' => 'https://evil.example.com',
        ])->assertRedirect(route('owner.dashboard'));
    }

    public function test_authenticated_owner_is_redirected_away_from_login(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->get('/owner/login')
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_logout_invalidates_session(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->post('/owner/logout')
            ->assertRedirect(route('owner.login'));

        $this->assertGuest('owner');
    }

    public function test_logout_must_be_post_not_get(): void
    {
        $this->get('/owner/logout')->assertStatus(405);
    }

    public function test_guest_cannot_reach_dashboard(): void
    {
        $this->get('/owner')->assertRedirect(route('owner.login'));
    }

    public function test_guest_cannot_reach_business_pages(): void
    {
        $this->get('/owner/outlets')->assertRedirect(route('owner.login'));
        $this->get('/owner/devices')->assertRedirect(route('owner.login'));
        $this->get('/owner/subscription')->assertRedirect(route('owner.login'));
        $this->get('/owner/usage')->assertRedirect(route('owner.login'));
        $this->get('/owner/operations')->assertRedirect(route('owner.login'));
    }

    public function test_owner_can_reach_console(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')->get('/owner')->assertOk();
        $this->actingAs($owner, 'owner')->get('/owner/outlets')->assertOk();
        $this->actingAs($owner, 'owner')->get('/owner/devices')->assertOk();
    }

    public function test_deactivated_owner_session_is_denied(): void
    {
        $owner = $this->owner(['is_active' => false]);

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertRedirect(route('owner.login'));

        $this->assertGuest('owner');
    }

    public function test_owner_whose_role_changed_is_denied(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['role' => User::ROLE_CASHIER])->save();

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertRedirect(route('owner.login'));

        $this->assertGuest('owner');
    }
}
