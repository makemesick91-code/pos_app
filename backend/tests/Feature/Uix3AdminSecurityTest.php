<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-3 — security-focused assertions for the admin console surface.
 */
class Uix3AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_carries_csrf_token(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_login_page_is_noindex(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_failed_login_does_not_reflect_password(): void
    {
        User::factory()->platformAdmin()->create(['email' => 'ops@aish.test']);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => 'ops@aish.test',
            'password' => 'ThisSecretMustNotAppear99',
        ]);

        // The repopulated login page must never echo the submitted password.
        $this->followingRedirects()
            ->from('/admin/login')
            ->get('/admin/login')
            ->assertDontSee('ThisSecretMustNotAppear99');
    }

    public function test_console_pages_render_no_password_hashes(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $html = $this->actingAs($admin, 'web')->get('/admin')->getContent();

        $this->assertStringNotContainsString('$2y$', $html);
        $this->assertStringNotContainsStringIgnoringCase('remember_token', $html);
    }

    public function test_logout_form_carries_csrf_token(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')->get('/admin')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }
}
