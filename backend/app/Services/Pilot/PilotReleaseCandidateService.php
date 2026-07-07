<?php

namespace App\Services\Pilot;

use App\Services\Release\ProductionReadinessService;
use App\Services\Release\ReleaseGateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.
 *
 * Aggregates the pilot release candidate contract into a single GO / WATCH /
 * NO-GO decision. It verifies that the pilot RC/UAT documentation, Sprint 13
 * release docs, release services, pilot commands, and regression routes exist,
 * folds in the Sprint 13 ReleaseGateService decision, and folds in the operator
 * UAT summary decision.
 *
 * It performs NO long external processes (no Android Gradle build) — CI runs
 * assembleDebug/testDebugUnitTest as the authoritative build gate. No secret
 * values are ever included in the output.
 *
 * Decision:
 *   GO    — every required check passes.
 *   WATCH — only non-critical warnings present.
 *   NO-GO — any critical (blocking) failure present.
 */
class PilotReleaseCandidateService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(
        private ReleaseGateService $releaseGate,
        private OperatorUatSummaryService $uat,
    ) {
    }

    /**
     * @return array{
     *   decision:string,
     *   checks:array<int,array{key:string,status:string,message:string,blocking:bool}>,
     *   uat_summary:array
     * }
     */
    public function evaluate(): array
    {
        $uatSummary = $this->uat->evaluate();

        $checks = [
            $this->requiredDocsCheck(),
            $this->releaseDocsCheck(),
            $this->releaseServicesCheck(),
            $this->requiredCommandsCheck(),
            $this->regressionRoutesCheck(),
            $this->releaseGateCheck(),
            $this->operatorUatCheck($uatSummary['decision']),
        ];

        return [
            'decision' => $this->decision($checks),
            'checks' => $checks,
            'uat_summary' => $uatSummary,
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

    /** @return array{key:string,status:string,message:string,blocking:bool} */
    private function check(string $key, string $status, string $message, bool $blocking = true): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message, 'blocking' => $blocking];
    }

    /**
     * Repository root — the Laravel app lives in backend/ while docs live one
     * level up.
     */
    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
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

    private function requiredDocsCheck(): array
    {
        $docs = (array) config('pilot_uat.required_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->check('pilot.rc_docs', self::STATUS_PASS, count($docs).' pilot RC/UAT docs present.')
            : $this->check('pilot.rc_docs', self::STATUS_FAIL, 'Missing pilot RC/UAT docs: '.implode(', ', $missing));
    }

    private function releaseDocsCheck(): array
    {
        $docs = (array) config('pilot_uat.required_release_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->check('pilot.release_docs', self::STATUS_PASS, 'Sprint 13 release docs present.')
            : $this->check('pilot.release_docs', self::STATUS_FAIL, 'Missing Sprint 13 release docs: '.implode(', ', $missing));
    }

    private function releaseServicesCheck(): array
    {
        $required = [
            ProductionReadinessService::class,
            ReleaseGateService::class,
        ];
        $missing = array_values(array_filter($required, fn (string $class) => ! class_exists($class)));

        return $missing === []
            ? $this->check('pilot.release_services', self::STATUS_PASS, 'Release readiness services available.')
            : $this->check('pilot.release_services', self::STATUS_FAIL, 'Missing release services: '.implode(', ', $missing));
    }

    private function requiredCommandsCheck(): array
    {
        $required = (array) config('pilot_uat.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->check('pilot.commands', self::STATUS_PASS, count($required).' required commands registered.')
            : $this->check('pilot.commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function regressionRoutesCheck(): array
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
            ? $this->check('pilot.regression_routes', self::STATUS_PASS, count($required).' regression routes registered.')
            : $this->check('pilot.regression_routes', self::STATUS_FAIL, 'Missing regression routes: '.implode(', ', $missing));
    }

    private function releaseGateCheck(): array
    {
        $decision = $this->releaseGate->evaluate()['decision'];

        return match ($decision) {
            ReleaseGateService::DECISION_NO_GO => $this->check('pilot.release_gate', self::STATUS_FAIL, 'Release gate reported NO-GO.'),
            ReleaseGateService::DECISION_WATCH => $this->check('pilot.release_gate', self::STATUS_WARN, 'Release gate reported WATCH.', false),
            default => $this->check('pilot.release_gate', self::STATUS_PASS, 'Release gate reported GO.'),
        };
    }

    private function operatorUatCheck(string $decision): array
    {
        return match ($decision) {
            OperatorUatSummaryService::DECISION_NO_GO => $this->check('pilot.operator_uat', self::STATUS_FAIL, 'Operator UAT summary reported NO-GO (open blocker or failing scenario).'),
            OperatorUatSummaryService::DECISION_WATCH => $this->check('pilot.operator_uat', self::STATUS_WARN, 'Operator UAT summary reported WATCH.', false),
            default => $this->check('pilot.operator_uat', self::STATUS_PASS, 'Operator UAT summary reported GO.'),
        };
    }
}
