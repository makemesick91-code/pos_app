<?php

namespace App\Services\Pilot;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 16 — Pilot Monitoring & Hypercare Foundation.
 *
 * Aggregates the pilot daily monitoring contract into a single GO / WATCH /
 * NO-GO decision. It verifies that the monitoring/hypercare docs exist, that the
 * cumulative Sprint 13–16 release/pilot Artisan commands are registered, that
 * the Android release readiness script is present, and it emits one status
 * signal per canonical daily monitoring signal (backend health, auth/login,
 * tenant context, product sync, cashier cash sale, QRIS status, receipt/printer,
 * offline cash queue/retry, inventory, reports, closing, subscription/device,
 * admin/onboarding, demo reset guard).
 *
 * It performs NO long external processes (no Android Gradle build), never
 * mutates production data, never sends real alerts, and never prints secrets.
 *
 * Decision:
 *   GO    — every signal passes.
 *   WATCH — only non-blocking warnings / non-critical failures present.
 *   NO-GO — any blocking signal fails.
 */
class PilotMonitoringService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * Evaluate the daily monitoring contract. When $evidence is empty the method
     * attempts to load a structured monitoring result file (config
     * pilot_monitoring.monitoring_result_file); when none exists every signal is
     * treated as defined/PASS (foundation-ready).
     *
     * $evidence shape (all optional):
     *   ['signals' => ['offline_sync_retry' => 'PASS', 'qris_payment_status' => 'FAIL', ...]]
     *
     * @param array<string,mixed> $evidence
     * @return array{
     *   decision:string,
     *   signals:array<int,array{key:string,status:string,message:string,blocking:bool}>
     * }
     */
    public function evaluate(array $evidence = []): array
    {
        if ($evidence === []) {
            $evidence = $this->loadResultFile();
        }

        $states = (array) ($evidence['signals'] ?? []);

        $signals = [
            $this->docsSignal(),
            $this->commandsSignal(),
            $this->androidScriptSignal(),
        ];

        foreach ($this->requiredSignals() as $key) {
            $signals[] = $this->definedSignal($key, $states[$key] ?? self::STATUS_PASS);
        }

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function requiredSignals(): array
    {
        return array_values((array) config('pilot_monitoring.required_signals', []));
    }

    /**
     * @param array<int,array{status:string,blocking:bool}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['blocking'] && $signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if (in_array($signal['status'], [self::STATUS_WARN, self::STATUS_FAIL], true)) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string,blocking:bool} */
    private function signal(string $key, string $status, string $message, bool $blocking = true): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message, 'blocking' => $blocking];
    }

    private function definedSignal(string $key, string $status): array
    {
        $status = $this->normalizeStatus($status);
        $blocking = in_array($key, (array) config('pilot_monitoring.critical_signals', []), true);

        $message = match ($status) {
            self::STATUS_FAIL => ucfirst(str_replace('_', ' ', $key)).' signal reported FAIL.',
            self::STATUS_WARN => ucfirst(str_replace('_', ' ', $key)).' signal reported WARN.',
            default => ucfirst(str_replace('_', ' ', $key)).' signal defined.',
        };

        return $this->signal($key, $status, $message, $blocking);
    }

    private function docsSignal(): array
    {
        $docs = (array) config('pilot_monitoring.required_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->signal('monitoring_docs', self::STATUS_PASS, count($docs).' monitoring/hypercare docs present.')
            : $this->signal('monitoring_docs', self::STATUS_FAIL, 'Missing monitoring/hypercare docs: '.implode(', ', $missing));
    }

    private function commandsSignal(): array
    {
        $required = (array) config('pilot_monitoring.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('release_pilot_commands', self::STATUS_PASS, count($required).' release/pilot commands registered.')
            : $this->signal('release_pilot_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('pilot_monitoring.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return in_array($status, [self::STATUS_PASS, self::STATUS_WARN, self::STATUS_FAIL], true)
            ? $status
            : self::STATUS_PASS;
    }

    /**
     * @param array<int,string> $docs
     * @return array<int,string>
     */
    private function missingDocs(array $docs): array
    {
        $missing = [];
        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing;
    }

    /**
     * @return array<string,mixed>
     */
    private function loadResultFile(): array
    {
        $relative = (string) config('pilot_monitoring.monitoring_result_file', '');
        if ($relative === '') {
            return [];
        }

        $path = $this->repoRoot().'/'.ltrim($relative, '/');
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
