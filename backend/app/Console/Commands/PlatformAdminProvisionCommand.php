<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * UIX-3 — provision (or promote) a platform administrator securely.
 *
 * Security requirements enforced here:
 * - NO production default credentials. There is no seeded admin@… / password.
 * - The password is NEVER accepted as a visible CLI argument (it would leak via
 *   shell history / process list). It is read from a hidden prompt, or, for
 *   non-interactive/secure automation, one line from STDIN via --stdin-password.
 * - Password strength is validated (length + composition + not obviously weak).
 * - The password is hashed with the framework hasher; the plaintext is never
 *   logged, echoed, or stored.
 * - Idempotent: an existing user is promoted/updated with explicit output.
 *
 * Usage:
 *   php artisan platform:admin-provision --email=ops@example.com --name="Ops"
 *   printf 'S3cret...' | php artisan platform:admin-provision --email=... --stdin-password
 */
class PlatformAdminProvisionCommand extends Command
{
    protected $signature = 'platform:admin-provision
        {--email= : Email address of the platform admin}
        {--name= : Display name (defaults to the existing name or the email local-part)}
        {--stdin-password : Read the password from a single STDIN line instead of prompting}
        {--rotate-password : When the user already exists, also set a new password}';

    protected $description = 'Securely create or promote a platform administrator (no default credentials).';

    /** Obviously-weak values that must never be accepted, regardless of length. */
    private const FORBIDDEN = [
        'password', 'password1', 'admin123', 'changeme', 'changeme123',
        'administrator', 'letmein', 'qwerty123', 'aish-pos', 'platform',
    ];

    public function handle(): int
    {
        $email = trim((string) ($this->option('email') ?: $this->ask('Platform admin email')));

        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();
        $isNew = $existing === null;

        // A new admin always needs a password; an existing one only if rotating.
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
            'is_platform_admin' => true,
            'platform_admin_granted_at' => now(),
            'platform_admin_revoked_at' => null,
        ];

        if ($password !== null) {
            $attributes['password'] = Hash::make($password);
        }

        if ($isNew) {
            $attributes['email'] = $email;
            $attributes['role'] = User::ROLE_SAAS_ADMIN;
            $attributes['tenant_id'] = null;
            $attributes['store_id'] = null;
            $attributes['email_verified_at'] = now();
            $user = User::query()->create($attributes);
        } else {
            $existing->forceFill($attributes)->save();
            $user = $existing;
        }

        // Never log the password. Attribute-only, redacted operational record.
        Log::info('platform.admin.provisioned', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower($email)),
            'created' => $isNew,
            'password_rotated' => $password !== null,
        ]);

        $this->info(sprintf(
            'Platform admin %s: %s (id=%d).%s',
            $isNew ? 'created' : 'updated',
            $email,
            $user->id,
            $password !== null ? ' Password set.' : ' Password unchanged.',
        ));

        return self::SUCCESS;
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
