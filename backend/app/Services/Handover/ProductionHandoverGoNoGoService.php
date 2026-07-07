<?php

namespace App\Services\Handover;

use App\Models\PilotClosureRun;
use App\Models\ProductionHandoverPackage;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 18 — production handover GO / WATCH / NO_GO aggregation.
 *
 * Combines the cumulative prior-sprint gate contract (Sprint 13 release, 14
 * RC/UAT, 15 deployment/field, 16 monitoring/hypercare, 17 stabilization
 * commands registered), the closure/handover documentation contract, the final
 * defect review, the final accepted-risk review, the latest pilot closure run,
 * the latest production handover package, and its sign-off summary into a single
 * decision.
 *
 *   NO_GO — any blocking signal fails, a required gate/command is missing, an
 *           open blocking defect without valid accepted risk, an expired blocking
 *           accepted risk, a rejected sign-off, or a BLOCKED handover package.
 *   WATCH — no blocking failure but a warning exists (major defect, valid
 *           accepted risk, approved-with-risk sign-off, missing closure/package,
 *           or a required role not yet signed off).
 *   GO    — every signal passes.
 *
 * Never prints secrets, never deploys, never sends real alerts, never runs Gradle.
 */
class ProductionHandoverGoNoGoService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(
        private readonly FinalDefectReviewService $defectReview,
        private readonly AcceptedRiskFinalReviewService $riskReview,
        private readonly ProductionHandoverService $handover,
        private readonly ProductionSignoffService $signoff,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $defect = $this->defectReview->review();
        $risk = $this->riskReview->review();
        $handoverEval = $this->handover->evaluate();

        $closure = PilotClosureRun::query()->latest('id')->first();
        $package = ProductionHandoverPackage::query()->latest('id')->first();
        $signoffSummary = $package !== null
            ? $this->signoff->summary($package)
            : ['decision' => ProductionSignoffService::DECISION_WATCH, 'missing_roles' => (array) config('production_handover.required_signoff_roles', [])];

        $signals = [
            $this->commandsSignal(),
            $this->androidScriptSignal(),
            $this->handoverDocsSignal($handoverEval),
            $this->decisionSignal('final_defect_review', $defect['decision']),
            $this->decisionSignal('accepted_risk_review', $risk['decision']),
            $this->closureSignal($closure),
            $this->packageSignal($package),
            $this->signoffSignal($signoffSummary['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'final_defect_review' => $defect,
            'accepted_risk_review' => $risk,
            'signoff_summary' => $signoffSummary,
            'closure' => $closure === null ? null : ['reference' => $closure->closure_reference, 'status' => $closure->status, 'decision' => $closure->decision],
            'package' => $package === null ? null : ['reference' => $package->handover_reference, 'status' => $package->status, 'decision' => $package->decision],
            'gates' => $this->gateReferences(),
        ];
    }

    /**
     * @param array<int,array{status:string}> $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function commandsSignal(): array
    {
        $required = (array) config('production_handover.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('release_pilot_handover_commands', self::STATUS_PASS, count($required).' release/pilot/handover commands registered.')
            : $this->signal('release_pilot_handover_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('production_handover.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    /** @param array<string,mixed> $handoverEval */
    private function handoverDocsSignal(array $handoverEval): array
    {
        return match ((string) $handoverEval['decision']) {
            ProductionHandoverService::DECISION_NO_GO => $this->signal('handover_docs', self::STATUS_FAIL, 'Missing required handover documentation.'),
            ProductionHandoverService::DECISION_WATCH => $this->signal('handover_docs', self::STATUS_WARN, 'Handover documentation has warnings.'),
            default => $this->signal('handover_docs', self::STATUS_PASS, 'Handover documentation complete.'),
        };
    }

    private function decisionSignal(string $key, string $decision): array
    {
        return match ($decision) {
            'NO_GO' => $this->signal($key, self::STATUS_FAIL, "{$key} is NO_GO."),
            'WATCH' => $this->signal($key, self::STATUS_WARN, "{$key} is WATCH."),
            default => $this->signal($key, self::STATUS_PASS, "{$key} is GO."),
        };
    }

    private function closureSignal(?PilotClosureRun $closure): array
    {
        if ($closure === null) {
            return $this->signal('pilot_closure', self::STATUS_WARN, 'No pilot closure run recorded yet.');
        }

        return match ($closure->decision) {
            PilotClosureRun::DECISION_NO_GO => $this->signal('pilot_closure', self::STATUS_FAIL, 'Latest pilot closure is NO_GO.'),
            PilotClosureRun::DECISION_WATCH => $this->signal('pilot_closure', self::STATUS_WARN, 'Latest pilot closure is WATCH.'),
            default => $this->signal('pilot_closure', self::STATUS_PASS, 'Latest pilot closure is GO.'),
        };
    }

    private function packageSignal(?ProductionHandoverPackage $package): array
    {
        if ($package === null) {
            return $this->signal('handover_package', self::STATUS_WARN, 'No production handover package recorded yet.');
        }

        return match ($package->status) {
            ProductionHandoverPackage::STATUS_BLOCKED => $this->signal('handover_package', self::STATUS_FAIL, 'Latest handover package is BLOCKED.'),
            ProductionHandoverPackage::STATUS_WATCH => $this->signal('handover_package', self::STATUS_WARN, 'Latest handover package is WATCH.'),
            ProductionHandoverPackage::STATUS_READY,
            ProductionHandoverPackage::STATUS_HANDED_OVER => $this->signal('handover_package', self::STATUS_PASS, 'Latest handover package is '.$package->status.'.'),
            default => $this->signal('handover_package', self::STATUS_WARN, 'Latest handover package is '.$package->status.'.'),
        };
    }

    private function signoffSignal(string $decision): array
    {
        return match ($decision) {
            ProductionSignoffService::DECISION_NO_GO => $this->signal('signoffs', self::STATUS_FAIL, 'A sign-off is REJECTED.'),
            ProductionSignoffService::DECISION_WATCH => $this->signal('signoffs', self::STATUS_WARN, 'Sign-offs pending or approved-with-risk.'),
            default => $this->signal('signoffs', self::STATUS_PASS, 'All required roles approved.'),
        };
    }

    /**
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
            'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
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
