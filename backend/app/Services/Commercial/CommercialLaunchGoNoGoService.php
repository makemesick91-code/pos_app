<?php

namespace App\Services\Commercial;

use App\Models\CommercialLaunchRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 20 — commercial launch GO / WATCH / NO_GO aggregation.
 *
 * Combines the cumulative prior-sprint gate contract (Sprint 13 release, 14
 * RC/UAT, 15 deployment/field, 16 monitoring/hypercare, 17 stabilization, 18
 * closure/handover, 19 production operations commands registered), the commercial
 * documentation contract, the Android release readiness script, and the full
 * commercial launch readiness evaluation (package catalog, pricing governance,
 * sales enablement, onboarding capacity, risk review, launch signoff) into a
 * single commercial launch decision.
 *
 *   NO_GO — any required prior gate/command/doc is missing, no active package, a
 *           blocking pricing/onboarding issue, an open CRITICAL/HIGH risk without a
 *           valid accepted risk, or a rejected signoff.
 *   WATCH — no blocking failure but a warning exists (open MEDIUM risk with
 *           mitigation, approved-with-risk signoff, or a non-critical package /
 *           pricing / onboarding warning).
 *   GO    — every signal passes.
 *
 * Never prints secrets, never deploys, never bills a real customer, never opens
 * public signup, never sends real alerts, never runs Android Gradle.
 */
class CommercialLaunchGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly CommercialLaunchReadinessService $readiness,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $readiness = $this->readiness->evaluate($now);

        $signals = [
            $this->commandsSignal(),
            $this->docsSignal(),
            $this->androidScriptSignal(),
            $this->decisionSignal('package_catalog', (string) $readiness['package_catalog']['decision']),
            $this->decisionSignal('pricing_governance', (string) $readiness['pricing_governance']['decision']),
            $this->decisionSignal('sales_enablement', (string) $readiness['sales_enablement']['decision']),
            $this->decisionSignal('onboarding_capacity', (string) $readiness['onboarding_capacity']['decision']),
            $this->decisionSignal('risk_review', (string) $readiness['risk_review']['decision']),
            $this->decisionSignal('launch_signoff', (string) $readiness['launch_signoff']['decision']),
            $this->decisionSignal('commercial_readiness', (string) $readiness['decision']),
        ];

        $latestRun = CommercialLaunchRun::query()->latest('id')->first();

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'commercial_readiness' => $readiness,
            'latest_launch_run' => $latestRun === null ? null : [
                'reference' => $latestRun->launch_reference,
                'status' => $latestRun->status,
                'decision' => $latestRun->decision,
            ],
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

    private function decisionSignal(string $key, string $decision): array
    {
        return match ($decision) {
            self::DECISION_NO_GO => $this->signal($key, self::STATUS_FAIL, "{$key} is NO_GO."),
            self::DECISION_WATCH => $this->signal($key, self::STATUS_WARN, "{$key} is WATCH."),
            default => $this->signal($key, self::STATUS_PASS, "{$key} is GO."),
        };
    }

    private function commandsSignal(): array
    {
        $required = (array) config('commercial_launch.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' release/pilot/handover/operations commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('commercial_launch.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('commercial_docs', self::STATUS_PASS, count($required).' commercial docs present.')
            : $this->signal('commercial_docs', self::STATUS_FAIL, 'Missing commercial docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('commercial_launch.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    /**
     * @return array<string,bool>
     */
    private function gateReferences(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('commercial_launch.prior_sprint_gates', []);

        $out = [];
        foreach ($gates as $name => $commands) {
            $out[$name] = array_diff((array) $commands, $registered) === [];
        }

        return $out;
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
