<?php

namespace App\Services\Pilot;

use App\Services\Release\ProductionReadinessService;
use App\Services\Release\ReleaseGateService;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.
 *
 * Aggregates the pilot deployment readiness contract into a single GO / WATCH /
 * NO-GO decision. It verifies that the pilot deployment / field trial docs,
 * Sprint 13 release docs, Sprint 14 RC/UAT docs, release/pilot services, and the
 * required Artisan commands exist, that the Android release readiness script is
 * present, folds in the Sprint 13 ReleaseGateService decision, the Sprint 14
 * PilotReleaseCandidateService decision, and the FieldTrialEvidenceService
 * decision.
 *
 * It performs NO long external processes (no Android Gradle build) and never
 * performs a real deploy — CI runs assembleDebug/testDebugUnitTest as the
 * authoritative build gate. No secret values are ever included in the output.
 *
 * Decision:
 *   GO    — every required check passes.
 *   WATCH — only non-critical warnings present.
 *   NO-GO — any critical (blocking) failure present.
 */
class PilotDeploymentReadinessService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(
        private ReleaseGateService $releaseGate,
        private PilotReleaseCandidateService $rc,
        private FieldTrialEvidenceService $field,
    ) {
    }

    /**
     * @return array{
     *   decision:string,
     *   checks:array<int,array{key:string,status:string,message:string,blocking:bool}>,
     *   field_summary:array
     * }
     */
    public function evaluate(): array
    {
        $rcReport = $this->rc->evaluate();
        $fieldSummary = $this->field->evaluate();

        $checks = [
            $this->requiredDocsCheck(),
            $this->releaseDocsCheck(),
            $this->rcDocsCheck(),
            $this->requiredCommandsCheck(),
            $this->servicesCheck(),
            $this->androidReadinessScriptCheck(),
            $this->releaseGateCheck(),
            $this->rcGateCheck($rcReport['decision']),
            $this->fieldTrialCheck($fieldSummary['decision']),
        ];

        return [
            'decision' => $this->decision($checks),
            'checks' => $checks,
            'field_summary' => $fieldSummary,
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
        $docs = (array) config('pilot_deployment.required_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->check('pilot.deployment_docs', self::STATUS_PASS, count($docs).' pilot deployment/field trial docs present.')
            : $this->check('pilot.deployment_docs', self::STATUS_FAIL, 'Missing pilot deployment/field trial docs: '.implode(', ', $missing));
    }

    private function releaseDocsCheck(): array
    {
        $docs = (array) config('pilot_deployment.required_release_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->check('pilot.release_docs', self::STATUS_PASS, 'Sprint 13 release docs present.')
            : $this->check('pilot.release_docs', self::STATUS_FAIL, 'Missing Sprint 13 release docs: '.implode(', ', $missing));
    }

    private function rcDocsCheck(): array
    {
        $docs = (array) config('pilot_deployment.required_rc_docs', []);
        $missing = $this->missingDocs($docs);

        return $missing === []
            ? $this->check('pilot.rc_docs', self::STATUS_PASS, 'Sprint 14 RC/UAT docs present.')
            : $this->check('pilot.rc_docs', self::STATUS_FAIL, 'Missing Sprint 14 RC/UAT docs: '.implode(', ', $missing));
    }

    private function requiredCommandsCheck(): array
    {
        $required = (array) config('pilot_deployment.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->check('pilot.commands', self::STATUS_PASS, count($required).' required commands registered.')
            : $this->check('pilot.commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function servicesCheck(): array
    {
        $required = [
            ProductionReadinessService::class,
            ReleaseGateService::class,
            PilotReleaseCandidateService::class,
            FieldTrialEvidenceService::class,
        ];
        $missing = array_values(array_filter($required, fn (string $class) => ! class_exists($class)));

        return $missing === []
            ? $this->check('pilot.services', self::STATUS_PASS, 'Release and pilot services available.')
            : $this->check('pilot.services', self::STATUS_FAIL, 'Missing services: '.implode(', ', $missing));
    }

    private function androidReadinessScriptCheck(): array
    {
        $script = (string) config('pilot_deployment.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->check('pilot.android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->check('pilot.android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
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

    private function rcGateCheck(string $decision): array
    {
        return match ($decision) {
            PilotReleaseCandidateService::DECISION_NO_GO => $this->check('pilot.rc_gate', self::STATUS_FAIL, 'Pilot RC/UAT gate reported NO-GO.'),
            PilotReleaseCandidateService::DECISION_WATCH => $this->check('pilot.rc_gate', self::STATUS_WARN, 'Pilot RC/UAT gate reported WATCH.', false),
            default => $this->check('pilot.rc_gate', self::STATUS_PASS, 'Pilot RC/UAT gate reported GO.'),
        };
    }

    private function fieldTrialCheck(string $decision): array
    {
        return match ($decision) {
            FieldTrialEvidenceService::DECISION_NO_GO => $this->check('pilot.field_trial', self::STATUS_FAIL, 'Field trial evidence reported NO-GO (open blocker/critical issue).'),
            FieldTrialEvidenceService::DECISION_WATCH => $this->check('pilot.field_trial', self::STATUS_WARN, 'Field trial evidence reported WATCH.', false),
            default => $this->check('pilot.field_trial', self::STATUS_PASS, 'Field trial evidence reported GO.'),
        };
    }
}
