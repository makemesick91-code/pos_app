<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UIX-4 — owner web console security posture: CSRF, noindex, no secret/hash
 * leakage, non-cacheable authenticated pages, generic errors (UIX4-R013/R014/R016).
 */
class Uix4OwnerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $attrs = []): User
    {
        return User::factory()->tenantOwner()->create(array_merge([
            'tenant_id' => Tenant::factory()->create()->id,
        ], $attrs));
    }

    public function test_login_form_carries_csrf_token(): void
    {
        $this->get('/owner/login')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_login_page_is_noindex(): void
    {
        $this->get('/owner/login')
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_logout_form_carries_csrf_token(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_failed_login_does_not_reflect_password(): void
    {
        $this->owner(['email' => 'owner@aish.test']);

        $this->from('/owner/login')
            ->post('/owner/login', ['email' => 'owner@aish.test', 'password' => 'SuperSecretValue123'])
            ->assertRedirect('/owner/login');

        $this->followingRedirects();
        $response = $this->get('/owner/login');
        $response->assertDontSee('SuperSecretValue123');
    }

    public function test_authenticated_console_pages_are_not_cacheable(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner, 'owner')->get('/owner');
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_console_pages_render_no_password_hashes(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'owner')
            ->get('/owner')
            ->assertOk()
            ->assertDontSee('$2y$', false);
    }
}
