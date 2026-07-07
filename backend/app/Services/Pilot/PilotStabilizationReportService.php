<?php

namespace App\Services\Pilot;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 17 — stabilization GO / WATCH / NO-GO aggregation.
 *
 * Combines the defect burn-down decision, SLA breach summary, accepted-risk
 * summary, and fix-verification/retest summary with the cumulative gate contract
 * (Sprint 13 release, Sprint 14 RC/UAT, Sprint 15 deployment/field, Sprint 16
 * monitoring/hypercare, Sprint 17 stabilization commands registered), the
 * stabilization docs, and the Android release readiness script.
 *
 * Decision:
 *   NO-GO — any blocking signal fails, OR the burn-down decision is NO-GO.
 *   WATCH — no blocking failure but the burn-down is WATCH or a warning exists.
 *   GO    — every signal passes and the burn-down is GO.
 *
 * Never prints secrets, never mutates production data, never sends real alerts.
 */
class PilotStabilizationReportService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(
        private readonly DefectBurnDownService $burnDown,
        private readonly SlaBreachDetectionService $sla,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $burnDown = $this->burnDown->summary();
        $sla = $this->sla->summary();

        $signals = [
            $this->docsSignal(),
            $this->commandsSignal(),
            $this->androidScriptSignal(),
            $this->burnDownSignal($burnDown),
            $this->slaSignal($sla),
        ];

        return [
            'decision' => $this->decision($signals, $burnDown['decision']),
            'signals' => $signals,
            'burndown' => $burnDown,
            'sla' => $sla,
            'gates' => $this->gateReferences(),
        ];
    }

    /**
     * @param array<int,array{status:string,blocking:bool}> $signals
     */
    private function decision(array $signals, string $burnDownDecision): string
    {
        foreach ($signals as $signal) {
            if ($signal['blocking'] && $signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        if ($burnDownDecision === DefectBurnDownService::DECISION_NO_GO) {
            return self::DECISION_NO_GO;
        }

        if ($burnDownDecision === DefectBurnDownService::DECISION_WATCH) {
            return self::DECISION_WATCH;
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

    private function docsSignal(): array
    {
        $docs = (array) config('pilot_stabilization.required_docs', []);
        $missing = [];
        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('stabilization_docs', self::STATUS_PASS, count($docs).' stabilization docs present.')
            : $this->signal('stabilization_docs', self::STATUS_FAIL, 'Missing stabilization docs: '.implode(', ', $missing));
    }

    private function commandsSignal(): array
    {
        $required = (array) config('pilot_stabilization.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('release_pilot_commands', self::STATUS_PASS, count($required).' release/pilot/stabilization commands registered.')
            : $this->signal('release_pilot_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('pilot_stabilization.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    /** @param array<string,mixed> $burnDown */
    private function burnDownSignal(array $burnDown): array
    {
        $counts = $burnDown['counts'] ?? [];
        $decision = (string) ($burnDown['decision'] ?? self::DECISION_GO);

        return match ($decision) {
            DefectBurnDownService::DECISION_NO_GO => $this->signal(
                'defect_burndown',
                self::STATUS_FAIL,
                'Open blocking defect(s) without valid accepted risk: '.($counts['open_blocking'] ?? 0),
            ),
            DefectBurnDownService::DECISION_WATCH => $this->signal(
                'defect_burndown',
                self::STATUS_WARN,
                'Major/accepted-risk defects present — WATCH.',
                false,
            ),
            default => $this->signal('defect_burndown', self::STATUS_PASS, 'No blocking open defects.'),
        };
    }

    /** @param array<string,mixed> $sla */
    private function slaSignal(array $sla): array
    {
        $count = (int) ($sla['breached_count'] ?? 0);

        return $count === 0
            ? $this->signal('sla_breach', self::STATUS_PASS, 'No SLA breaches.', false)
            : $this->signal('sla_breach', self::STATUS_WARN, "{$count} SLA-breached open defect(s).", false);
    }

    /**
     * References to the cumulative prior-sprint gate commands (registered = the
     * gate is wired; CI executes them). No command is run here.
     *
     * @return array<string,bool>
     */
    private function gateReferences(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = [
            'release_gate' => ['production:readiness-check', 'release:go-no-go'],
            'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
            'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
            'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
            'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        ];

        $out = [];
        foreach ($gates as $name => $commands) {
            $out[$name] = array_diff($commands, $registered) === [];
        }

        return $out;
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
