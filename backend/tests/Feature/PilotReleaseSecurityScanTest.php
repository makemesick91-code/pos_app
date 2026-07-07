<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 14 — pilot release security scan: pilot tooling and docs must not leak
 * secrets or real credentials, the repository must not ship build artifacts or
 * signing keys, and the Android app must not gain an admin/onboarding/UAT panel.
 */
class PilotReleaseSecurityScanTest extends TestCase
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

    /**
     * @return array<int,string>
     */
    private function pilotDocs(): array
    {
        return glob($this->repoRoot().'/docs/pilot/*.md') ?: [];
    }

    public function test_pilot_commands_do_not_expose_secrets(): void
    {
        config(['app.key' => 'base64:SUPERSECRETPILOTKEY123456789012345678901234=']);

        Artisan::call('pilot:rc-check', ['--json' => true]);
        $rc = Artisan::output();

        Artisan::call('pilot:uat-summary', ['--json' => true]);
        $uat = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETPILOTKEY', $rc);
        $this->assertStringNotContainsString('SUPERSECRETPILOTKEY', $uat);
    }

    public function test_pilot_docs_have_no_real_secret_values(): void
    {
        $docs = $this->pilotDocs();
        $this->assertNotEmpty($docs, 'Pilot docs must exist under docs/pilot/.');

        foreach ($docs as $doc) {
            $content = (string) file_get_contents($doc);
            $name = basename($doc);

            $this->assertStringNotContainsString('base64:', $content, "{$name} must not embed an APP_KEY value.");
            $this->assertDoesNotMatchRegularExpression(
                '/(MIDTRANS_SERVER_KEY|XENDIT_SECRET_KEY|DUITKU_API_KEY|QRIS_FAKE_WEBHOOK_SECRET|APP_KEY)\s*=\s*\S+/',
                $content,
                "{$name} must not assign a real secret value."
            );
        }
    }

    public function test_pilot_docs_use_placeholder_credentials(): void
    {
        $checklist = $this->repoRoot().'/docs/pilot/operator-uat-checklist.md';
        $this->assertFileExists($checklist);
        $content = (string) file_get_contents($checklist);

        $this->assertStringContainsString('operator@example.test', $content);
        $this->assertStringContainsString('DEMO_TENANT_PLACEHOLDER', $content);
    }

    public function test_no_apk_aab_or_keystore_tracked(): void
    {
        foreach ($this->trackedFiles() as $file) {
            $this->assertStringEndsNotWith('.apk', $file);
            $this->assertStringEndsNotWith('.aab', $file);
            $this->assertStringEndsNotWith('.keystore', $file);
            $this->assertStringEndsNotWith('.jks', $file);
        }
    }

    public function test_android_has_no_admin_onboarding_or_uat_panel(): void
    {
        $hits = $this->androidGrep('-E "AdminActivity|OnboardingActivity|UatActivity"');

        $this->assertSame('', trim($hits), "Android must not contain an admin/onboarding/UAT panel:\n{$hits}");
    }
}
