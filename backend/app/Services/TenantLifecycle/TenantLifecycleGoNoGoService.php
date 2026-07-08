<?php

namespace App\Services\TenantLifecycle;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 25 — tenant lifecycle enforcement & manual suspension GO / WATCH /
 * NO_GO aggregation.
 *
 * Combines the cumulative Sprint 13–24 gate contract (all prior release/pilot/
 * handover/operations/commercial/public-website/sales-pipeline/billing-collection/
 * subscription-renewal commands registered), the tenant lifecycle documentation
 * contract, the Android release readiness script, and the full tenant lifecycle
 * readiness (which itself embeds the runtime enforcement audit) into a single
 * decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a payment
 * gateway, never auto-suspends/reactivates a tenant, never sends a real message,
 * never runs Android Gradle.
 */
class TenantLifecycleGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly TenantLifecycleReadinessService $readiness,
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
            $this->decisionSignal('tenant_lifecycle_readiness', (string) $readiness['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'tenant_lifecycle_readiness' => $readiness,
        ];
    }

    private function commandsSignal(): array
    {
        $required = (array) config('tenant_lifecycle.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' prior-sprint commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function ownCommandsSignal(): array
    {
        $required = (array) config('tenant_lifecycle.tenant_lifecycle_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('tenant_lifecycle_commands', self::STATUS_PASS, count($required).' tenant lifecycle commands registered.')
            : $this->signal('tenant_lifecycle_commands', self::STATUS_FAIL, 'Missing tenant lifecycle commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('tenant_lifecycle.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('tenant_lifecycle_docs', self::STATUS_PASS, count($required).' tenant lifecycle docs present.')
            : $this->signal('tenant_lifecycle_docs', self::STATUS_FAIL, 'Missing tenant lifecycle docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('tenant_lifecycle.android_release_readiness_script', '');
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
        $gates = (array) config('tenant_lifecycle.prior_sprint_gates', []);

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
