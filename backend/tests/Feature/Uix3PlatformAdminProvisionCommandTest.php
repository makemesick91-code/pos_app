<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UIX-3 — secure platform-admin provisioning command.
 */
class Uix3PlatformAdminProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_platform_admin_with_hashed_password(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'ops@aish.test', '--name' => 'Ops'])
            ->expectsQuestion('Password (hidden)', 'S3cureConsole2026')
            ->expectsQuestion('Confirm password (hidden)', 'S3cureConsole2026')
            ->assertSuccessful();

        $user = User::query()->where('email', 'ops@aish.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue($user->is_active);
        // Stored as a framework hash, never plaintext.
        $this->assertNotSame('S3cureConsole2026', $user->password);
        $this->assertTrue(Hash::check('S3cureConsole2026', $user->password));
    }

    public function test_rejects_password_shorter_than_12_chars(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'ops@aish.test'])
            ->expectsQuestion('Password (hidden)', 'short1')
            ->expectsQuestion('Confirm password (hidden)', 'short1')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ops@aish.test']);
    }

    public function test_rejects_password_without_letter_and_digit(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'ops@aish.test'])
            ->expectsQuestion('Password (hidden)', 'aaaaaaaaaaaaaa')
            ->expectsQuestion('Confirm password (hidden)', 'aaaaaaaaaaaaaa')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ops@aish.test']);
    }

    public function test_rejects_mismatched_confirmation(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'ops@aish.test'])
            ->expectsQuestion('Password (hidden)', 'S3cureConsole2026')
            ->expectsQuestion('Confirm password (hidden)', 'DifferentValue99')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'ops@aish.test']);
    }

    public function test_rejects_password_containing_account_name(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'operator@aish.test'])
            ->expectsQuestion('Password (hidden)', 'operator12345')
            ->expectsQuestion('Confirm password (hidden)', 'operator12345')
            ->assertFailed();
    }

    public function test_promotes_existing_user_without_password_prompt(): void
    {
        $user = User::factory()->tenantOwner()->create([
            'email' => 'promote@aish.test',
            'tenant_id' => Tenant::factory()->create()->id,
        ]);
        $originalHash = $user->password;

        // No --rotate-password → no password question, password unchanged.
        $this->artisan('platform:admin-provision', ['--email' => 'promote@aish.test'])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_platform_admin);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_rotate_password_updates_existing_admin(): void
    {
        $user = User::factory()->platformAdmin()->create(['email' => 'ops@aish.test']);
        $originalHash = $user->password;

        $this->artisan('platform:admin-provision', ['--email' => 'ops@aish.test', '--rotate-password' => true])
            ->expectsQuestion('Password (hidden)', 'R0tatedConsole2026')
            ->expectsQuestion('Confirm password (hidden)', 'R0tatedConsole2026')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('R0tatedConsole2026', $user->password));
    }

    public function test_requires_valid_email(): void
    {
        $this->artisan('platform:admin-provision', ['--email' => 'not-an-email'])
            ->assertFailed();
    }
}
