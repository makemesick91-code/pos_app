<?php

namespace App\Services\TenantPlan;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 26 — tenant plan / feature entitlement / usage-limit governance GO /
 * WATCH / NO_GO aggregation (TPE-R011).
 *
 * Combines the cumulative Sprint 13–25 gate contract (all prior release/pilot/
 * handover/operations/commercial/public-website/sales-pipeline/billing/renewal/
 * lifecycle commands registered), the Sprint 26 command contract, the tenant plan
 * documentation contract, the Android release readiness script, and the full
 * tenant plan readiness (which embeds the runtime enforcement audit) into a single
 * decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a payment
 * gateway, never auto-suspends/reactivates a tenant, never runs Android Gradle.
 */
class TenantPlanGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly TenantPlanReadinessService $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $readiness = $this->readiness->evaluate();

        $signals = [
            $this->commandsSignal(),
            $this->ownCommandsSignal(),
            $this->docsSignal(),
            $this->androidScriptSignal(),
            $this->decisionSignal('tenant_plan_readiness', (string) $readiness['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'tenant_plan_readiness' => $readiness,
        ];
    }

    private function commandsSignal(): array
    {
        $required = (array) config('tenant_plan.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' prior-sprint commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('tenant_plan.tenant_plan_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('tenant_plan_commands', self::STATUS_PASS, count($required).' tenant plan commands registered.')
            : $this->signal('tenant_plan_commands', self::STATUS_FAIL, 'Missing tenant plan commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('tenant_plan.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('tenant_plan_docs', self::STATUS_PASS, count($required).' tenant plan docs present.')
            : $this->signal('tenant_plan_docs', self::STATUS_FAIL, 'Missing tenant plan docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('tenant_plan.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    private function decisionSignal(string $key, string $decision): array
    {
        return match ($decision) {
            self::DECISION_NO_GO => $this->signal($key, self::STATUS_FAIL, "{$key} is NO_GO."),
            self::DECISION_WATCH => $this->signal($key, self::STATUS_WARN, "{$key} is WATCH."),
            default => $this->signal($key, self::STATUS_PASS, "{$key} is GO."),
        };
    }

    /**
     * @return array<string, bool>
     */
    private function gateReferences(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('tenant_plan.prior_sprint_gates', []);

        $out = [];
        foreach ($gates as $name => $commands) {
            $out[$name] = array_diff((array) $commands, $registered) === [];
        }

        return $out;
    }

    /**
     * @param  array<int, array{status:string}>  $signals
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

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
