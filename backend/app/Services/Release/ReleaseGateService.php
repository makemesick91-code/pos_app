<?php

namespace App\Services\Release;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Sprint 13 — Production Readiness & Release Hardening Foundation.
 *
 * Aggregates backend release readiness into a single GO / WATCH / NO-GO
 * decision. It combines ProductionReadinessService with static contract checks
 * (required docs, required routes, required commands, forbidden committed
 * files). It intentionally performs NO long external processes (no Android
 * Gradle build) — CI runs assembleDebug/testDebugUnitTest as the build gate.
 *
 * Decision:
 *   GO    — every required check passes.
 *   WATCH — only non-critical warnings present.
 *   NO-GO — any critical failure present.
 *
 * No secret values are ever included in the output.
 */
class ReleaseGateService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(private ProductionReadinessService $readiness)
    {
    }

    /**
     * @return array{decision:string, checks:array<int,array{key:string,status:string,message:string}>, production_readiness:array}
     */
    public function evaluate(): array
    {
        $readiness = $this->readiness->evaluate();

        $checks = [
            $this->requiredDocsCheck(),
            $this->requiredRoutesCheck(),
            $this->requiredCommandsCheck(),
            $this->forbiddenFilesCheck(),
            $this->productionReadinessCheck($readiness['overall_status']),
        ];

        return [
            'decision' => $this->decision($checks),
            'checks' => $checks,
            'production_readiness' => $readiness,
        ];
    }

    /**
     * @param array<int,array{status:string}> $checks
     */
    private function decision(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_FAIL, $statuses, true)) {
            return self::DECISION_NO_GO;
        }

        if (in_array(self::STATUS_WARN, $statuses, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function check(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    /**
     * Repository root — the Laravel app lives in backend/ while docs/scripts
     * live one level up.
     */
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }

    private function requiredDocsCheck(): array
    {
        $docs = (array) config('release_readiness.required_docs', []);
        $missing = [];

        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->check('required.docs', self::STATUS_PASS, count($docs).' required docs present.')
            : $this->check('required.docs', self::STATUS_FAIL, 'Missing required docs: '.implode(', ', $missing));
    }

    private function requiredRoutesCheck(): array
    {
        $required = (array) config('release_readiness.required_routes', []);
        $registered = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
        $missing = [];

        foreach ($required as $uri) {
            $target = ltrim((string) $uri, '/');
            $found = false;

            foreach ($registered as $registeredUri) {
                if ($registeredUri === $target || str_starts_with($registeredUri, $target.'/') || str_starts_with($registeredUri, $target.'/{')) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missing[] = $uri;
            }
        }

        return $missing === []
            ? $this->check('required.routes', self::STATUS_PASS, count($required).' required routes registered.')
            : $this->check('required.routes', self::STATUS_FAIL, 'Missing required routes: '.implode(', ', $missing));
    }

    private function requiredCommandsCheck(): array
    {
        $required = (array) config('release_readiness.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->check('required.commands', self::STATUS_PASS, count($required).' required commands registered.')
            : $this->check('required.commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    /**
     * Ensure forbidden patterns are not tracked by git. Uses `git ls-files` from
     * the repo root; if git is unavailable the check downgrades to WARN so it
     * never crashes a release evaluation.
     */
    private function forbiddenFilesCheck(): array
    {
        $patterns = (array) config('release_readiness.forbidden_files', []);

        try {
            $tracked = $this->trackedFiles();
        } catch (Throwable) {
            return $this->check('forbidden.files', self::STATUS_WARN, 'Could not enumerate git-tracked files (git unavailable).');
        }

        $offenders = [];
        foreach ($tracked as $file) {
            $base = basename($file);
            foreach ($patterns as $pattern) {
                if ($base === $pattern || fnmatch((string) $pattern, $base) || fnmatch((string) $pattern, $file)) {
                    // gradle-wrapper.jar is the single allowed committed binary.
                    if ($base === 'gradle-wrapper.jar') {
                        continue;
                    }
                    $offenders[] = $file;
                    break;
                }
            }
        }

        return $offenders === []
            ? $this->check('forbidden.files', self::STATUS_PASS, 'No forbidden files are tracked.')
            : $this->check('forbidden.files', self::STATUS_FAIL, 'Forbidden files tracked: '.implode(', ', array_unique($offenders)));
    }

    /**
     * @return array<int,string>
     */
    private function trackedFiles(): array
    {
        $root = $this->repoRoot();
        $output = [];
        $status = 1;

        exec('git -C '.escapeshellarg($root).' ls-files 2>/dev/null', $output, $status);

        if ($status !== 0) {
            throw new \RuntimeException('git ls-files failed');
        }

        return $output;
    }

    private function productionReadinessCheck(string $overall): array
    {
        return match ($overall) {
            ProductionReadinessService::STATUS_FAIL => $this->check('production.readiness', self::STATUS_FAIL, 'Production readiness reported FAIL.'),
            ProductionReadinessService::STATUS_WARN => $this->check('production.readiness', self::STATUS_WARN, 'Production readiness reported WARN.'),
            default => $this->check('production.readiness', self::STATUS_PASS, 'Production readiness reported PASS.'),
        };
    }
}
