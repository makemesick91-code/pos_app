<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sprint 36 — database / cache / storage / config diagnostics (OBS-R005/R006/R007).
 *
 * Each probe returns ONLY a safe status ('ok' | 'error') + a driver/connection
 * NAME. It never returns a DB credential or DSN (OBS-R005), a cache key or value
 * (OBS-R006), or a raw filesystem path (OBS-R007). On failure the exception class
 * is reported but never its message (which may embed a credential).
 */
class InfrastructureHealthCheckService
{
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'error';

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $cfg = (array) config('observability_governance.infrastructure', []);

        $checks = [];
        if (($cfg['check_database'] ?? true)) {
            $checks['database'] = $this->database();
        }
        if (($cfg['check_cache'] ?? true)) {
            $checks['cache'] = $this->cache();
        }
        if (($cfg['check_storage'] ?? true)) {
            $checks['storage'] = $this->storage();
        }
        if (($cfg['check_config'] ?? true)) {
            $checks['config'] = $this->configSanity();
        }

        $healthy = true;
        foreach ($checks as $check) {
            if (($check['status'] ?? self::STATUS_ERROR) !== self::STATUS_OK) {
                $healthy = false;
            }
        }

        return [
            'status' => $healthy ? self::STATUS_OK : self::STATUS_ERROR,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function database(): array
    {
        try {
            DB::connection()->select('select 1 as ok');

            return [
                'status' => self::STATUS_OK,
                // Connection NAME only — never the DSN / credentials.
                'connection' => (string) config('database.default'),
                'driver' => (string) DB::connection()->getDriverName(),
            ];
        } catch (Throwable $e) {
            return ['status' => self::STATUS_ERROR, 'error_class' => class_basename($e)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cache(): array
    {
        try {
            $probeKey = 'observability:probe';
            Cache::put($probeKey, 1, 5);
            $roundTrip = Cache::get($probeKey) === 1;
            Cache::forget($probeKey);

            return [
                'status' => $roundTrip ? self::STATUS_OK : self::STATUS_ERROR,
                // Store NAME only — never a key or a value.
                'store' => (string) config('cache.default'),
            ];
        } catch (Throwable $e) {
            return ['status' => self::STATUS_ERROR, 'error_class' => class_basename($e)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function storage(): array
    {
        try {
            $dir = (string) (config('observability_governance.infrastructure.storage_probe_directory') ?? 'observability-probe');
            $key = $dir.'/.probe';
            Storage::put($key, 'ok');
            $ok = Storage::get($key) === 'ok';
            Storage::delete($key);

            return [
                'status' => $ok ? self::STATUS_OK : self::STATUS_ERROR,
                // Disk NAME only — never an absolute path.
                'disk' => (string) config('filesystems.default'),
            ];
        } catch (Throwable $e) {
            return ['status' => self::STATUS_ERROR, 'error_class' => class_basename($e)];
        }
    }

    /**
     * Environment/config sanity WITHOUT exposing any secret value: report only
     * booleans (is the app key set, is debug off in production, etc.).
     *
     * @return array<string, mixed>
     */
    public function configSanity(): array
    {
        $appKeySet = is_string(config('app.key')) && config('app.key') !== '';
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $debugSafe = $env !== 'production' || $debug === false;

        return [
            'status' => ($appKeySet && $debugSafe) ? self::STATUS_OK : self::STATUS_ERROR,
            'app_key_set' => $appKeySet,
            'env' => $env,
            'debug_safe_for_env' => $debugSafe,
        ];
    }
}
