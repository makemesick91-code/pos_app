<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 19 — static safety scan. Operations docs must not leak real credentials;
 * the command/service layer must not print secrets, send real alerts, deploy, or
 * run real backup/restore; no forbidden Android UI/artifact may be introduced.
 */
class ProductionOperationsSecurityScanTest extends TestCase
{
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    public function test_operations_docs_have_no_real_secrets(): void
    {
        $docs = (array) config('production_operations.required_docs', []);
        $docs[] = 'docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md';

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
            'ProductionOpsHealthCommand',
            'ProductionIncidentSummaryCommand',
            'ProductionBackupGovernanceCheckCommand',
            'ProductionPostHandoverGoNoGoCommand',
        ] as $cmd) {
            $content = (string) file_get_contents("{$commandDir}/{$cmd}.php");
            $this->assertStringNotContainsString("env('APP_KEY", $content);
            $this->assertStringNotContainsString('MIDTRANS_SERVER_KEY', $content);
        }
    }

    public function test_no_real_alert_deploy_or_backup_restore_introduced(): void
    {
        $serviceDir = base_path('app/Services/Operations');
        foreach (glob("{$serviceDir}/*.php") ?: [] as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString('Http::post(', $content, "Unexpected outbound HTTP in {$file}");
            $this->assertStringNotContainsString('->send(', $content, "Unexpected notification send in {$file}");
            $this->assertStringNotContainsString('shell_exec(', $content, "Unexpected shell exec in {$file}");
            $this->assertStringNotContainsString('Artisan::call(\'migrate', $content, "Unexpected migrate in {$file}");
        }
    }

    public function test_no_apk_aab_keystore_or_env_tracked(): void
    {
        $tracked = shell_exec('cd '.escapeshellarg($this->repoRoot()).' && git ls-files') ?? '';
        $this->assertDoesNotMatchRegularExpression('/\.(apk|aab|keystore|jks)$/m', $tracked);
        $this->assertDoesNotMatchRegularExpression('/(^|\/)\.env$/m', $tracked);
    }
}
