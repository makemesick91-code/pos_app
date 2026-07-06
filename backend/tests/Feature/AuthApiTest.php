<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(string $tenantStatus = Tenant::STATUS_ACTIVE, bool $active = true): User
    {
        $tenant = Tenant::factory()->create(['status' => $tenantStatus]);

        return User::factory()->tenantOwner()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.com',
            'is_active' => $active,
        ]);
    }

    public function test_active_tenant_user_can_login(): void
    {
        $this->makeOwner();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user', 'tenant'])
            ->assertJsonPath('user.role', User::ROLE_TENANT_OWNER)
            ->assertJsonPath('tenant.status', Tenant::STATUS_ACTIVE);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = $this->makeOwner();
        $this->assertNull($user->last_login_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeOwner(active: false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_suspended_tenant_user_cannot_login(): void
    {
        $this->makeOwner(tenantStatus: Tenant::STATUS_SUSPENDED);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeOwner();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_me_returns_user_and_tenant(): void
    {
        $user = $this->makeOwner();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('tenant.id', $user->tenant_id)
            ->assertJsonPath('foundation', 'POS_ANDROID_SAAS_FOUNDATION');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->makeOwner();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->json('token');

        $auth = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/auth/me', $auth)->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/v1/auth/logout', [], $auth)
            ->assertOk()
            ->assertJsonPath('success', true);

        // The Sanctum access token row is deleted -> token no longer valid.
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_login_response_does_not_leak_password(): void
    {
        $this->makeOwner();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }
}
