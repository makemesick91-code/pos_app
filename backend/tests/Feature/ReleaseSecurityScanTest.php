<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 13 — release security scan: release tooling must not leak secrets, and
 * the repository must not ship secrets, build artifacts, or an Android
 * admin/onboarding panel.
 */
class ReleaseSecurityScanTest extends TestCase
{
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    /**
     * @return array<int,string>
     */
    private function trackedFiles(): array
    {
        $output = [];
        exec('git -C '.escapeshellarg($this->repoRoot()).' ls-files 2>/dev/null', $output);

        return $output;
    }

    private function androidGrep(string $pattern): string
    {
        $root = escapeshellarg($this->repoRoot());
        $cmd = "grep -RIl {$pattern} {$root}/android/app/src/main/java {$root}/android/app/src/main/res 2>/dev/null";

        return (string) shell_exec($cmd);
    }

    public function test_release_commands_do_not_expose_secrets(): void
    {
        config([
            'app.key' => 'base64:SUPERSECRETKEYVALUE123456789012345678901234=',
        ]);

        Artisan::call('production:readiness-check', ['--json' => true]);
        $readiness = Artisan::output();

        Artisan::call('release:go-no-go', ['--json' => true]);
        $gate = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $readiness);
        $this->assertStringNotContainsString('SUPERSECRETKEYVALUE', $gate);
    }

    public function test_android_source_has_no_payment_gateway_secrets(): void
    {
        $hits = $this->androidGrep('-E "MIDTRANS_SERVER_KEY|XENDIT_SECRET_KEY|DUITKU_API_KEY|QRIS_FAKE_WEBHOOK_SECRET"');

        $this->assertSame('', trim($hits), "Android source must not contain payment gateway secrets:\n{$hits}");
    }

    public function test_android_has_no_admin_or_onboarding_panel(): void
    {
        $hits = $this->androidGrep('-E "AdminActivity|OnboardingActivity|TenantOnboarding"');

        $this->assertSame('', trim($hits), "Android must not contain an admin/onboarding panel:\n{$hits}");
    }

    public function test_no_env_or_build_artifacts_committed(): void
    {
        $tracked = $this->trackedFiles();

        foreach ($tracked as $file) {
            $base = basename($file);

            $this->assertNotSame('.env', $base, ".env must not be committed ({$file}).");
            $this->assertStringEndsNotWith('.apk', $file);
            $this->assertStringEndsNotWith('.aab', $file);
            $this->assertStringEndsNotWith('.keystore', $file);
            $this->assertStringEndsNotWith('.jks', $file);

            if ($base !== 'gradle-wrapper.jar') {
                $this->assertStringEndsNotWith('.jar', $file, "Unexpected committed jar: {$file}.");
            }
        }
    }
}
