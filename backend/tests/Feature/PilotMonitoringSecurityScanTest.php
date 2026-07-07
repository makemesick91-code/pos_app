<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 16 — pilot monitoring / hypercare security scan: monitoring tooling and
 * docs must not leak secrets or real credentials, the repository must not ship
 * build artifacts or signing keys, and the Android app must not gain an admin /
 * onboarding / UAT / deployment / monitoring / hypercare panel.
 */
class PilotMonitoringSecurityScanTest extends TestCase
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
     * Sprint 16 pilot monitoring / hypercare docs.
     *
     * @return array<int,string>
     */
    private function monitoringDocs(): array
    {
        $names = [
            'daily-monitoring-runbook.md',
            'hypercare-issue-triage-workflow.md',
            'field-issue-severity-sla.md',
            'operator-feedback-log.md',
            'pilot-health-summary-template.md',
            'hypercare-go-watch-no-go-report.md',
            'failed-sync-monitoring-checklist.md',
            'payment-qris-monitoring-checklist.md',
            'device-subscription-anomaly-checklist.md',
            'closing-report-monitoring-checklist.md',
        ];

        return array_map(fn (string $n) => $this->repoRoot().'/docs/pilot/'.$n, $names);
    }

    public function test_monitoring_commands_do_not_expose_secrets(): void
    {
        config(['app.key' => 'base64:SUPERSECRETMONITORINGKEY1234567890123456789=']);

        Artisan::call('pilot:daily-monitoring-check', ['--json' => true]);
        $daily = Artisan::output();

        Artisan::call('pilot:health-summary', ['--json' => true]);
        $health = Artisan::output();

        Artisan::call('hypercare:issue-triage', ['--json' => true]);
        $triage = Artisan::output();

        $this->assertStringNotContainsString('SUPERSECRETMONITORINGKEY', $daily);
        $this->assertStringNotContainsString('SUPERSECRETMONITORINGKEY', $health);
        $this->assertStringNotContainsString('SUPERSECRETMONITORINGKEY', $triage);
    }

    public function test_monitoring_docs_have_no_real_secret_values(): void
    {
        foreach ($this->monitoringDocs() as $doc) {
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

    public function test_operator_feedback_log_uses_placeholders(): void
    {
        $log = $this->repoRoot().'/docs/pilot/operator-feedback-log.md';
        $this->assertFileExists($log);
        $content = (string) file_get_contents($log);

        $this->assertStringContainsString('operator@example.test', $content);
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

    public function test_android_has_no_admin_monitoring_or_hypercare_panel(): void
    {
        $hits = $this->androidGrep('-E "AdminActivity|OnboardingActivity|UatActivity|DeploymentActivity|MonitoringActivity|HypercareActivity"');

        $this->assertSame('', trim($hits), "Android must not contain an admin/onboarding/UAT/deployment/monitoring/hypercare panel:\n{$hits}");
    }
}
