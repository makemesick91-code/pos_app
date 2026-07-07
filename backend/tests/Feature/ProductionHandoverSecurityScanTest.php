<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 18 — static safety scan. Handover docs must not leak real credentials;
 * the command/service layer must not print secrets, send real alerts, or deploy;
 * no forbidden Android UI/artifact may be introduced.
 */
class ProductionHandoverSecurityScanTest extends TestCase
{
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    public function test_handover_docs_have_no_real_secrets(): void
    {
        $docs = (array) config('production_handover.required_docs', []);
        $docs[] = 'docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md';

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
        foreach ([
            'PilotClosureCheckCommand',
            'ProductionHandoverSummaryCommand',
            'ProductionSignoffSummaryCommand',
            'ProductionHandoverGoNoGoCommand',
        ] as $cmd) {
            $content = (string) file_get_contents("{$commandDir}/{$cmd}.php");
            $this->assertStringNotContainsString("env('APP_KEY", $content);
            $this->assertStringNotContainsString('MIDTRANS_SERVER_KEY', $content);
        }
    }

    public function test_no_real_alert_sending_or_deploy_introduced(): void
    {
        $serviceDir = base_path('app/Services/Handover');
        foreach (glob("{$serviceDir}/*.php") ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString('Http::post(', $content, "Unexpected outbound HTTP in {$file}");
            $this->assertStringNotContainsString('->send(', $content, "Unexpected notification send in {$file}");
            $this->assertStringNotContainsString('shell_exec(', $content, "Unexpected shell exec in {$file}");
        }
    }

    public function test_no_apk_aab_keystore_or_env_tracked(): void
    {
        $tracked = shell_exec('cd '.escapeshellarg($this->repoRoot()).' && git ls-files') ?? '';
        $this->assertDoesNotMatchRegularExpression('/\.(apk|aab|keystore|jks)$/m', $tracked);
        $this->assertDoesNotMatchRegularExpression('/(^|\/)\.env$/m', $tracked);
    }

    public function test_no_android_handover_or_admin_ui(): void
    {
        $androidSrc = $this->repoRoot().'/android/app/src/main/java';
        if (! is_dir($androidSrc)) {
            $this->markTestSkipped('Android source not present in this checkout.');
        }

        $matches = shell_exec(
            'grep -rl "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity\|StabilizationActivity\|HandoverActivity\|ProductionActivity" '
            .escapeshellarg($androidSrc).' 2>/dev/null'
        );

        $this->assertEmpty(trim((string) $matches), 'No Android admin/handover/production UI may exist.');
    }
}
