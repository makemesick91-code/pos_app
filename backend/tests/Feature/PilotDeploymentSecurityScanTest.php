<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 15 — pilot deployment security scan: pilot deployment tooling and docs
 * must not leak secrets or real credentials, the repository must not ship build
 * artifacts or signing keys, and the Android app must not gain an admin /
 * onboarding / UAT / deployment panel.
 */
class PilotDeploymentSecurityScanTest extends TestCase
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
     * Sprint 15 pilot deployment / field trial docs.
     *
     * @return array<int,string>
     */
    private function pilotDeploymentDocs(): array
    {
        $names = [
            'pilot-deployment-checklist.md',
            'field-trial-evidence-pack.md',
            'backend-deployment-dry-run.md',
            'android-rc-artifact-handling.md',
            'operator-device-readiness.md',
            'demo-tenant-pilot-setup-evidence.md',
            'post-deploy-smoke-checklist.md',
            'pilot-rollback-checklist.md',
            'daily-pilot-monitoring-checklist.md',
            'field-issue-register.md',
            'field-trial-go-watch-no-go-report.md',
        ];

        return array_map(fn (string $n) => $this->repoRoot().'/docs/pilot/'.$n, $names);
    }

    public function test_pilot_commands_do_not_expose_secrets(): void
    {
        config(['app.key' => 'base64:SUPERSECRETDEPLOYKEY123456789012345678901234=']);

        Artisan::call('pilot:deployment-check', ['--json' => true]);
        $deploy = Artisan::output();

        Artisan::call('pilot:field-trial-summary', ['--json' => true]);
        $field = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETDEPLOYKEY', $deploy);
        $this->assertStringNotContainsString('SUPERSECRETDEPLOYKEY', $field);
    }

    public function test_pilot_deployment_docs_have_no_real_secret_values(): void
    {
        foreach ($this->pilotDeploymentDocs() as $doc) {
            $this->assertFileExists($doc);
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

    public function test_pilot_deployment_docs_use_placeholders(): void
    {
        $pack = $this->repoRoot().'/docs/pilot/field-trial-evidence-pack.md';
        $this->assertFileExists($pack);
        $content = (string) file_get_contents($pack);

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

    public function test_android_has_no_admin_onboarding_uat_or_deployment_panel(): void
    {
        $hits = $this->androidGrep('-E "AdminActivity|OnboardingActivity|UatActivity|DeploymentActivity"');

        $this->assertSame('', trim($hits), "Android must not contain an admin/onboarding/UAT/deployment panel:\n{$hits}");
    }
}
