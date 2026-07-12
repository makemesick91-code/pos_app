<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * UIX-4 — provision (or promote) a Tenant Owner for the Owner Web Console
 * securely.
 *
 * Security requirements enforced here (UIX4-R012/R013):
 * - NO production default credentials. There is no seeded owner@… / password.
 * - The tenant is selected by a safe identifier (code or id) and must already
 *   exist; the command never creates a tenant or opens self-service signup.
 * - The password is NEVER accepted as a visible CLI argument (it would leak via
 *   shell history / process list). It is read from a hidden prompt, or one line
 *   from STDIN via --stdin-password for secure automation.
 * - Password strength is validated (length + composition + not obviously weak).
 * - The password is hashed with the framework hasher; the plaintext is never
 *   logged, echoed, or stored.
 * - Idempotent: an existing owner is updated with explicit output. A platform
 *   admin identity is never granted here.
 *
 * Usage:
 *   php artisan tenant:owner-provision --tenant=ACME01 --email=owner@example.com --name="Owner"
 *   printf 'S3cret...' | php artisan tenant:owner-provision --tenant=ACME01 --email=... --stdin-password
 */
class TenantOwnerProvisionCommand extends Command
{
    protected $signature = 'tenant:owner-provision
        {--tenant= : Tenant code or id that this owner belongs to}
        {--email= : Email address of the tenant owner}
        {--name= : Display name (defaults to the existing name or the email local-part)}
        {--stdin-password : Read the password from a single STDIN line instead of prompting}
        {--rotate-password : When the user already exists, also set a new password}';

    protected $description = 'Securely create or promote a tenant owner for the Owner Web Console (no default credentials).';

    /** Obviously-weak values that must never be accepted, regardless of length. */
    private const FORBIDDEN = [
        'password', 'password1', 'admin123', 'changeme', 'changeme123',
        'owner123', 'letmein', 'qwerty123', 'aish-pos', 'tenant',
    ];

    public function handle(): int
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $email = trim((string) ($this->option('email') ?: $this->ask('Tenant owner email')));

        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        // Never silently adopt an account that is not already a member of this
        // tenant. A platform admin is refused outright, and an existing user is
        // only updated when it already belongs to THIS tenant (e.g. promoting a
        // cashier to owner). A user with a different tenant — or none at all
        // (orphaned / saas_admin) — is refused; provision a fresh email instead.
        if ($existing !== null && $existing->isPlatformAdmin()) {
            $this->error('That email is a platform admin. Owner and platform-admin identities are kept separate.');

            return self::FAILURE;
        }

        if ($existing !== null && (int) ($existing->tenant_id ?? -1) !== (int) $tenant->id) {
            $this->error('That email is not a member of this tenant. Refusing to reassign; use a new email.');

            return self::FAILURE;
        }

        $isNew = $existing === null;
        $needsPassword = $isNew || (bool) $this->option('rotate-password');
        $password = null;

        if ($needsPassword) {
            $password = $this->readPassword();

            if ($password === null) {
                return self::FAILURE;
            }

            if (! $this->passwordIsStrong($password, $email)) {
                return self::FAILURE;
            }
        }

        $name = (string) ($this->option('name')
            ?: $existing?->name
            ?: ucfirst((string) strstr($email, '@', true)));

        $attributes = [
            'name' => $name,
            'is_active' => true,
            'is_platform_admin' => false,
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ];

        if ($password !== null) {
            $attributes['password'] = Hash::make($password);
        }

        if ($isNew) {
            $attributes['email'] = $email;
            $attributes['email_verified_at'] = now();
            $user = User::query()->create($attributes);
        } else {
            $existing->forceFill($attributes)->save();
            $user = $existing;
        }

        // Never log the password. Attribute-only, redacted operational record.
        Log::info('tenant.owner.provisioned', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'email_hash' => hash('sha256', strtolower($email)),
            'created' => $isNew,
            'password_rotated' => $password !== null,
        ]);

        $this->info(sprintf(
            'Tenant owner %s: %s for tenant %s (id=%d).%s',
            $isNew ? 'created' : 'updated',
            $email,
            $tenant->code,
            $user->id,
            $password !== null ? ' Password set.' : ' Password unchanged.',
        ));

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $identifier = trim((string) ($this->option('tenant') ?: $this->ask('Tenant code or id')));

        if ($identifier === '') {
            $this->error('A --tenant code or id is required.');

            return null;
        }

        $query = Tenant::query();
        $tenant = ctype_digit($identifier)
            ? $query->find((int) $identifier)
            : $query->where('code', $identifier)->first();

        if ($tenant === null) {
            $this->error('Tenant not found for the given identifier.');

            return null;
        }

        return $tenant;
    }

    private function readPassword(): ?string
    {
        if ($this->option('stdin-password')) {
            $line = fgets(STDIN);

            if ($line === false) {
                $this->error('No password received on STDIN.');

                return null;
            }

            return rtrim($line, "\r\n");
        }

        $password = (string) $this->secret('Password (hidden)');
        $confirm = (string) $this->secret('Confirm password (hidden)');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return null;
        }

        return $password;
    }

    private function passwordIsStrong(string $password, string $email): bool
    {
        if (strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return false;
        }

        if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $this->error('Password must contain at least one letter and one digit.');

            return false;
        }

        $lower = strtolower($password);

        if (in_array($lower, self::FORBIDDEN, true)) {
            $this->error('Password is too common.');

            return false;
        }

        if (str_contains($lower, strtolower((string) strstr($email, '@', true)))) {
            $this->error('Password must not contain the account name.');

            return false;
        }

        return true;
    }
}
