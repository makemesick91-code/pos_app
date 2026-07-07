<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 17 — static safety scan. Stabilization docs must not leak real
 * credentials; the command layer must not print secrets; no real alert channel
 * and no forbidden Android UI/artifact may be introduced.
 */
class PilotStabilizationSecurityScanTest extends TestCase
{
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    public function test_stabilization_docs_have_no_real_secrets(): void
    {
        $docs = (array) config('pilot_stabilization.required_docs', []);
        $docs[] = 'docs/pilot/stabilization-daily-checklist.md';
        $docs[] = 'docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md';

        foreach ($docs as $doc) {
            $path = $this->repoRoot().'/'.$doc;
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $this->assertDoesNotMatchRegularExpression('/sk_live_[A-Za-z0-9]+/', $content);
            $this->assertDoesNotMatchRegularExpression('/AKIA[0-9A-Z]{16}/', $content);
            $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $content);
        }
    }

    public function test_commands_do_not_reference_secret_env_values(): void
    {
        $commandDir = base_path('app/Console/Commands');
        foreach (['PilotDefectSummaryCommand', 'PilotBurndownSummaryCommand', 'PilotSlaCheckCommand', 'PilotStabilizationGoNoGoCommand'] as $cmd) {
            $content = (string) file_get_contents("{$commandDir}/{$cmd}.php");
            $this->assertStringNotContainsString("env('APP_KEY", $content);
            $this->assertStringNotContainsString('MIDTRANS_SERVER_KEY', $content);
        }
    }

    public function test_no_real_alert_sending_introduced(): void
    {
        $serviceDir = base_path('app/Services/Pilot');
        foreach (glob("{$serviceDir}/*.php") ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString('Http::post(', $content, "Unexpected outbound HTTP in {$file}");
            $this->assertStringNotContainsString('->send(', $content, "Unexpected notification send in {$file}");
        }
    }

    public function test_no_apk_aab_keystore_tracked(): void
    {
        $tracked = shell_exec('cd '.escapeshellarg($this->repoRoot()).' && git ls-files') ?? '';
        $this->assertDoesNotMatchRegularExpression('/\.(apk|aab|keystore|jks)$/m', $tracked);
        $this->assertDoesNotMatchRegularExpression('/(^|\/)\.env$/m', $tracked);
    }

    public function test_no_android_stabilization_or_admin_ui(): void
    {
        $androidSrc = $this->repoRoot().'/android/app/src/main/java';
        if (! is_dir($androidSrc)) {
            $this->markTestSkipped('Android source not present in this checkout.');
        }

        $matches = shell_exec(
            'grep -rl "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity\|StabilizationActivity" '
            .escapeshellarg($androidSrc).' 2>/dev/null'
        );

        $this->assertEmpty(trim((string) $matches), 'No Android admin/stabilization UI may exist.');
    }
}
