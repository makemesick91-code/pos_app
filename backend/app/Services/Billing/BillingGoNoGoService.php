<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 30 — billing:go-no-go aggregation (BIL-R015).
 *
 * Combines the Sprint 30 billing governance audit (tables, services, pricing,
 * rules, guardrails, data integrity, admin-only mutation routes) with the Sprint
 * 24–29 prior-sprint gate contract, the Sprint 30 command/doc contract, and the
 * Android release readiness script into a single GO/WATCH/NO_GO decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a payment
 * gateway, never auto-suspends/reactivates a tenant, never runs Android Gradle.
 */
class BillingGoNoGoService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_WARN = 'WARN';

    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';

    public const DECISION_WATCH = 'WATCH';

    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly BillingGovernanceAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $audit = $this->audit->evaluate();

        $signals = [
            $this->billingCommandsSignal(),
            $this->priorCommandsSignal(),
            $this->docsSignal(),
            $this->androidScriptSignal(),
            $this->decisionSignal('billing_governance_audit', (string) $audit['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'billing_governance_audit' => $audit,
        ];
    }

    private function billingCommandsSignal(): array
    {
        return $this->commandContractSignal(
            'billing_commands',
            (array) config('billing_governance.billing_commands', []),
            'billing commands',
        );
    }

    private function priorCommandsSignal(): array
    {
        return $this->commandContractSignal(
            'prior_sprint_commands',
            (array) config('billing_governance.required_commands', []),
            'prior-sprint gate commands',
        );
    }

    private function commandContractSignal(string $key, array $required, string $label): array
    {
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal($key, self::STATUS_PASS, count($required)." {$label} registered.")
            : $this->signal($key, self::STATUS_FAIL, "Missing {$label}: ".implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('billing_governance.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('billing_docs', self::STATUS_PASS, count($required).' billing docs present.')
            : $this->signal('billing_docs', self::STATUS_FAIL, 'Missing billing docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('billing_governance.android_release_readiness_script', '');
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
        $gates = (array) config('billing_governance.prior_sprint_gates', []);

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
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }
        foreach ($signals as $s) {
            if ($s['status'] === self::STATUS_WARN) {
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
