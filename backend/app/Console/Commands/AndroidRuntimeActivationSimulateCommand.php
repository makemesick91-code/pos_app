<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeSimulator;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:activation-simulate. Default DRY-RUN (no mutation).
 * With --execute it provisions an isolated throw-away tenant/device, activates it
 * twice with the same fingerprint and asserts idempotency (one device). Never
 * prints the raw activation token (ADR-R003).
 */
class AndroidRuntimeActivationSimulateCommand extends Command
{
    protected $signature = 'android-runtime:activation-simulate {--execute : Actually provision an isolated tenant and activate} {--json : Output JSON}';

    protected $description = 'Simulate a governed, idempotent Android device activation (dry-run by default).';

    public function handle(AndroidRuntimeSimulator $simulator): int
    {
        if (! $this->option('execute')) {
            $report = [
                'mode' => 'dry-run',
                'token_hash_algo' => config('android_runtime_governance.activation_token.hash_algo'),
                'raw_token_returned_after_creation' => config('android_runtime_governance.activation_token.return_raw_token_after_creation'),
                'idempotent_per_fingerprint' => true,
                'note' => 'Pass --execute to provision an isolated tenant and activate twice.',
            ];
            $this->output->write($this->render($report));

            return self::SUCCESS;
        }

        $result = $simulator->simulateActivation();
        $result['mode'] = 'execute';
        $this->output->write($this->render($result));

        $ok = ($result['idempotent'] ?? false) === true && (int) ($result['device_count'] ?? 0) === 1;

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function render(array $report): string
    {
        if ($this->option('json')) {
            return (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }

        $out = '';
        foreach ($report as $k => $v) {
            $out .= $k.'='.(is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? $v : json_encode($v))).PHP_EOL;
        }

        return $out;
    }
}
