<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UIX-4 — secure tenant-owner provisioning command (no default credentials).
 */
class Uix4TenantOwnerProvisionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_tenant_owner_with_hashed_password(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'ACME01']);

        $this->artisan('tenant:owner-provision', ['--tenant' => 'ACME01', '--email' => 'boss@aish.test', '--name' => 'Owner'])
            ->expectsQuestion('Password (hidden)', 'S3cureConsole2026')
            ->expectsQuestion('Confirm password (hidden)', 'S3cureConsole2026')
            ->assertSuccessful();

        $user = User::query()->where('email', 'boss@aish.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_TENANT_OWNER, $user->role);
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertFalse($user->is_platform_admin);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('S3cureConsole2026', $user->password);
        $this->assertTrue(Hash::check('S3cureConsole2026', $user->password));
    }

    public function test_command_has_no_visible_password_option(): void
    {
        $command = Artisan::all()['tenant:owner-provision'];
        $this->assertFalse($command->getDefinition()->hasOption('password'));
    }

    public function test_rejects_unknown_tenant(): void
    {
        $this->artisan('tenant:owner-provision', ['--tenant' => 'NOPE', '--email' => 'owner@aish.test'])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@aish.test']);
    }

    public function test_rejects_password_shorter_than_12_chars(): void
    {
        Tenant::factory()->create(['code' => 'ACME01']);

        $this->artisan('tenant:owner-provision', ['--tenant' => 'ACME01', '--email' => 'owner@aish.test'])
            ->expectsQuestion('Password (hidden)', 'short1')
            ->expectsQuestion('Confirm password (hidden)', 'short1')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@aish.test']);
    }

    public function test_rejects_common_password(): void
    {
        Tenant::factory()->create(['code' => 'ACME01']);

        $this->artisan('tenant:owner-provision', ['--tenant' => 'ACME01', '--email' => 'owner@aish.test'])
            ->expectsQuestion('Password (hidden)', 'changeme123')
            ->expectsQuestion('Confirm password (hidden)', 'changeme123')
            ->assertFailed();
    }

    public function test_is_idempotent_and_updates_existing_owner(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'ACME01']);
        $user = User::factory()->cashier()->create([
            'email' => 'promote@aish.test',
            'tenant_id' => $tenant->id,
        ]);

        // No --rotate-password → no password prompt; promotes role to owner.
        $this->artisan('tenant:owner-provision', ['--tenant' => 'ACME01', '--email' => 'promote@aish.test'])
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(User::ROLE_TENANT_OWNER, $user->role);
    }

    public function test_refuses_to_reassign_owner_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['code' => 'AAA']);
        Tenant::factory()->create(['code' => 'BBB']);
        User::factory()->tenantOwner()->create([
            'email' => 'owner@aish.test',
            'tenant_id' => $tenantA->id,
        ]);

        $this->artisan('tenant:owner-provision', ['--tenant' => 'BBB', '--email' => 'owner@aish.test'])
            ->assertFailed();

        $this->assertDatabaseHas('users', ['email' => 'owner@aish.test', 'tenant_id' => $tenantA->id]);
    }

    public function test_refuses_platform_admin_email(): void
    {
        Tenant::factory()->create(['code' => 'ACME01']);
        User::factory()->platformAdmin()->create(['email' => 'ops@aish.test']);

        $this->artisan('tenant:owner-provision', ['--tenant' => 'ACME01', '--email' => 'ops@aish.test'])
            ->assertFailed();
    }
}
