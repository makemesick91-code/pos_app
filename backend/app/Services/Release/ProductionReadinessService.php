<?php

namespace App\Services\Release;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sprint 13 — Production Readiness & Release Hardening Foundation.
 *
 * Inspects environment/runtime configuration and returns a structured,
 * secret-safe report of PASS/WARN/FAIL checks. It never prints or returns raw
 * secret values (APP_KEY, payment gateway credentials, DB passwords); sensitive
 * checks only report presence/shape and are flagged with `sensitive => true`.
 *
 * Statuses:
 *   PASS  — safe for a production release.
 *   WARN  — acceptable for local/dev, needs attention before production.
 *   FAIL  — dangerous setting that blocks a release.
 */
class ProductionReadinessService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /** Environments treated as production-like (strictest checks). */
    private const PRODUCTION_LIKE = ['production', 'prod'];

    /**
     * Run all readiness checks and return the aggregated report.
     *
     * @return array{overall_status:string, checks:array<int,array{key:string,status:string,message:string,sensitive:bool}>}
     */
    public function evaluate(): array
    {
        $checks = [
            $this->checkAppEnv(),
            $this->checkAppKey(),
            $this->checkAppDebug(),
            $this->checkDatabaseConnection(),
            $this->checkMigrations(),
            $this->checkCacheDriver(),
            $this->checkSessionDriver(),
            $this->checkQueueConnection(),
            $this->checkStorageWritable(),
            $this->checkLogsWritable(),
            $this->checkPaymentGatewaySecrets(),
            $this->checkFoundationLock(),
        ];

        return [
            'overall_status' => $this->overallStatus($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @param array<int,array{status:string}> $checks
     */
    private function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_FAIL, $statuses, true)) {
            return self::STATUS_FAIL;
        }

        if (in_array(self::STATUS_WARN, $statuses, true)) {
            return self::STATUS_WARN;
        }

        return self::STATUS_PASS;
    }

    private function isProductionLike(): bool
    {
        return in_array((string) config('app.env'), self::PRODUCTION_LIKE, true);
    }

    /** @return array{key:string,status:string,message:string,sensitive:bool} */
    private function check(string $key, string $status, string $message, bool $sensitive = false): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'message' => $message,
            'sensitive' => $sensitive,
        ];
    }

    private function checkAppEnv(): array
    {
        $env = (string) config('app.env');

        return $this->check('app.env', self::STATUS_PASS, "APP_ENV is '{$env}'.");
    }

    private function checkAppKey(): array
    {
        // Never expose the value; only report presence/shape.
        $key = (string) config('app.key');

        if ($key === '') {
            return $this->check('app.key', self::STATUS_FAIL, 'APP_KEY is not set. Run php artisan key:generate.', true);
        }

        return $this->check('app.key', self::STATUS_PASS, 'APP_KEY is configured (value redacted).', true);
    }

    private function checkAppDebug(): array
    {
        $debug = (bool) config('app.debug');

        if (! $debug) {
            return $this->check('app.debug', self::STATUS_PASS, 'APP_DEBUG is disabled.');
        }

        if ($this->isProductionLike()) {
            return $this->check('app.debug', self::STATUS_FAIL, 'APP_DEBUG is enabled in a production-like environment.');
        }

        return $this->check('app.debug', self::STATUS_WARN, 'APP_DEBUG is enabled (acceptable outside production).');
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->check('database.connection', self::STATUS_PASS, 'Database connection is reachable.');
        } catch (Throwable) {
            $status = $this->isProductionLike() ? self::STATUS_FAIL : self::STATUS_WARN;

            return $this->check('database.connection', $status, 'Database connection could not be established.');
        }
    }

    private function checkMigrations(): array
    {
        try {
            $repository = app('migration.repository');

            if (! $repository->repositoryExists()) {
                $status = $this->isProductionLike() ? self::STATUS_FAIL : self::STATUS_WARN;

                return $this->check('migrations.status', $status, 'Migration repository not found. Run php artisan migrate.');
            }

            $ran = $repository->getRan();

            return $this->check('migrations.status', self::STATUS_PASS, count($ran).' migration batch(es) applied.');
        } catch (Throwable) {
            $status = $this->isProductionLike() ? self::STATUS_FAIL : self::STATUS_WARN;

            return $this->check('migrations.status', $status, 'Migration status could not be determined.');
        }
    }

    private function checkCacheDriver(): array
    {
        $driver = (string) config('cache.default');

        return $driver === ''
            ? $this->check('cache.driver', self::STATUS_FAIL, 'Cache driver is not configured.')
            : $this->check('cache.driver', self::STATUS_PASS, "Cache driver is '{$driver}'.");
    }

    private function checkSessionDriver(): array
    {
        $driver = (string) config('session.driver');

        return $driver === ''
            ? $this->check('session.driver', self::STATUS_FAIL, 'Session driver is not configured.')
            : $this->check('session.driver', self::STATUS_PASS, "Session driver is '{$driver}'.");
    }

    private function checkQueueConnection(): array
    {
        $connection = (string) config('queue.default');

        if ($connection === '') {
            return $this->check('queue.connection', self::STATUS_FAIL, 'Queue connection is not configured.');
        }

        if ($connection === 'sync' && $this->isProductionLike()) {
            return $this->check('queue.connection', self::STATUS_WARN, "Queue connection is 'sync' in production-like env; consider a real queue.");
        }

        return $this->check('queue.connection', self::STATUS_PASS, "Queue connection is '{$connection}'.");
    }

    private function checkStorageWritable(): array
    {
        return $this->writabilityCheck('storage.writable', storage_path('app'), 'Storage path');
    }

    private function checkLogsWritable(): array
    {
        return $this->writabilityCheck('logs.writable', storage_path('logs'), 'Logs path');
    }

    /**
     * A non-writable path is FAIL in production-like environments and WARN
     * elsewhere (fresh CI checkouts may not have provisioned the directory).
     */
    private function writabilityCheck(string $key, string $path, string $label): array
    {
        if (is_writable($path)) {
            return $this->check($key, self::STATUS_PASS, "{$label} is writable.");
        }

        $status = $this->isProductionLike() ? self::STATUS_FAIL : self::STATUS_WARN;

        return $this->check($key, $status, "{$label} is not writable.");
    }

    private function checkPaymentGatewaySecrets(): array
    {
        // Report presence only — never echo the credential values.
        $default = (string) config('payment_gateway.default_qris_provider', 'fake');
        $configured = config("payment_gateway.providers.{$default}") !== null;

        if ($default === 'fake') {
            return $this->check('payment_gateway.secrets', self::STATUS_PASS, "Payment gateway is the fake/local provider (no real secrets required).", true);
        }

        return $configured
            ? $this->check('payment_gateway.secrets', self::STATUS_PASS, "Payment gateway '{$default}' provider config present (secrets redacted).", true)
            : $this->check('payment_gateway.secrets', self::STATUS_WARN, "Payment gateway '{$default}' provider config missing.", true);
    }

    private function checkFoundationLock(): array
    {
        $rules = (array) config('pos_foundation.rules', []);

        $required = [
            'release_readiness_gate_required',
            'production_env_safety_check_required',
        ];

        foreach ($required as $flag) {
            if (($rules[$flag] ?? false) !== true) {
                return $this->check('foundation.lock', self::STATUS_WARN, "Foundation rule flag '{$flag}' is not enabled.");
            }
        }

        return $this->check('foundation.lock', self::STATUS_PASS, 'Release readiness foundation flags are enabled.');
    }
}
