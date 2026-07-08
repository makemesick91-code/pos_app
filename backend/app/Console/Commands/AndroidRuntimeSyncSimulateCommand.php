<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeSimulator;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:sync-simulate. Deterministic sync scenario runner.
 * Default DRY-RUN describes the scenario; --execute provisions an isolated tenant
 * and runs the real sync through the canonical gate. Asserts the expected
 * invariant per scenario (returns non-zero on mismatch). No PII/secrets.
 *
 * Scenarios: valid | replay | duplicate-item | conflict | revoked-device |
 * suspended-tenant | unpaid-past-grace | trial-expired.
 */
class AndroidRuntimeSyncSimulateCommand extends Command
{
    protected $signature = 'android-runtime:sync-simulate {--scenario=valid : Scenario to run} {--execute : Actually provision an isolated tenant and sync} {--json : Output JSON}';

    protected $description = 'Simulate a deterministic Android sync batch scenario (dry-run by default).';

    private const SCENARIOS = ['valid', 'replay', 'duplicate-item', 'conflict', 'revoked-device', 'suspended-tenant', 'unpaid-past-grace', 'trial-expired'];

    public function handle(AndroidRuntimeSimulator $simulator): int
    {
        $scenario = (string) $this->option('scenario');

        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->error('Unknown scenario. Allowed: '.implode(', ', self::SCENARIOS));

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->render(['mode' => 'dry-run', 'scenario' => $scenario, 'note' => 'Pass --execute to run the real sync on an isolated tenant.']);

            return self::SUCCESS;
        }

        $result = $simulator->simulateSync($scenario);
        $result['mode'] = 'execute';
        $this->render($result);

        return $this->assert($scenario, $result) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function assert(string $scenario, array $r): bool
    {
        $codes = (array) ($r['conflict_codes'] ?? []);

        return match ($scenario) {
            'valid' => $r['batch_status'] === 'completed' && (int) $r['accepted'] >= 1,
            'replay' => (bool) $r['idempotent_replay'] === true,
            'duplicate-item' => (int) $r['duplicate'] >= 1,
            'conflict' => $codes !== [],
            'revoked-device' => $r['batch_status'] === 'rejected' && in_array('device_revoked', $codes, true),
            'suspended-tenant' => $r['batch_status'] === 'rejected' && in_array('tenant_suspended', $codes, true),
            'unpaid-past-grace' => $r['batch_status'] === 'rejected' && in_array('unpaid_past_grace', $codes, true),
            'trial-expired' => $r['batch_status'] === 'rejected' && in_array('trial_expired', $codes, true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function render(array $report): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($report as $k => $v) {
            $this->line($k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? $v : json_encode($v))));
        }
    }
}
