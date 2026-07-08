<?php

namespace App\Services\Entitlements;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Sprint 32 — the hard entitlement runtime-enforcement GO / WATCH / NO_GO gate
 * (ENT-R024). Aggregates the governance audit, the runtime-wiring checks for all
 * core limits, the command self-contract, the cumulative Sprint 24–31 prior-gate
 * contract, and the documentation contract into one decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a gateway,
 * never auto-suspends/reactivates a tenant, never runs Android Gradle.
 */
class EntitlementGoNoGoService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_WARN = 'WARN';

    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';

    public const DECISION_WATCH = 'WATCH';

    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly EntitlementGovernanceAuditService $governance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $signals = array_merge(
            $this->governance->evaluate(),
            [
                $this->runtimeLimitsSignal(),
                $this->writeGateWiredSignal(),
                $this->ownCommandsSignal(),
                $this->priorCommandsSignal(),
                $this->docsSignal(),
                $this->androidScriptSignal(),
            ],
        );

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
        ];
    }

    private function runtimeLimitsSignal(): array
    {
        // ENT-R024 — every core limit alias must resolve to a real Sprint 26
        // usage-limit key and a resource/action so it can actually be enforced.
        $required = ['branch', 'user', 'cashier', 'device', 'outlet', 'register'];
        $limits = (array) config('entitlement_governance.limits', []);
        $missing = [];
        foreach ($required as $alias) {
            $meta = (array) ($limits[$alias] ?? []);
            if (($meta['limit_key'] ?? '') === '' || ($meta['resource'] ?? '') === '') {
                $missing[] = $alias;
            }
        }

        return $missing === []
            ? $this->signal('runtime_limits', self::STATUS_PASS, 'All core limits are wired to a plan usage key.')
            : $this->signal('runtime_limits', self::STATUS_FAIL, 'Unwired core limits: '.implode(', ', $missing));
    }

    private function writeGateWiredSignal(): array
    {
        $aliases = array_keys(Route::getMiddleware());
        $required = ['entitlement.write', 'entitlement.feature', 'entitlement.export', 'entitlement.report'];
        $missing = array_values(array_diff($required, $aliases));

        return $missing === []
            ? $this->signal('write_gate', self::STATUS_PASS, 'Entitlement enforcement middleware registered.')
            : $this->signal('write_gate', self::STATUS_FAIL, 'Missing enforcement middleware: '.implode(', ', $missing));
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('entitlement_governance.entitlement_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('entitlement_commands', self::STATUS_PASS, count($required).' entitlement commands registered.')
            : $this->signal('entitlement_commands', self::STATUS_FAIL, 'Missing entitlement commands: '.implode(', ', $missing));
    }

    private function priorCommandsSignal(): array
    {
        $required = (array) config('entitlement_governance.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' prior-sprint gate commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing prior-sprint commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('entitlement_governance.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('entitlement_docs', self::STATUS_PASS, count($required).' entitlement docs present.')
            : $this->signal('entitlement_docs', self::STATUS_FAIL, 'Missing entitlement docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('entitlement_governance.android_release_readiness_script', '');
        $exists = $script !== '' && is_file($this->repoRoot().'/'.ltrim($script, '/'));

        return $exists
            ? $this->signal('android_release_readiness', self::STATUS_PASS, 'Android release readiness script present.')
            : $this->signal('android_release_readiness', self::STATUS_FAIL, 'Missing Android release readiness script: '.$script);
    }

    /**
     * @return array<string, bool>
     */
    private function gateReferences(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('entitlement_governance.prior_sprint_gates', []);

        $out = [];
        foreach ($gates as $name => $commands) {
            $out[$name] = array_diff((array) $commands, $registered) === [];
        }

        return $out;
    }

    /**
     * @param  array<int, array{status: string}>  $signals
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

    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
