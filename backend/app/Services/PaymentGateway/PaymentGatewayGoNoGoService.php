<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 31 — payment-gateway:go-no-go aggregation (PGW-R017).
 *
 * Combines the gateway governance audit (tables, services, rules, guardrails,
 * webhook posture, provider config, billing-layer compatibility, data integrity,
 * admin-only mutation routes, no tenant mutation route) with the Sprint 31
 * command/doc contract and the Sprint 24–30 prior-sprint gate contract into a
 * single GO/WATCH/NO_GO decision.
 *
 * Never prints secrets, never deploys, never charges, never calls a real payment
 * gateway, never auto-suspends/reactivates a tenant.
 */
class PaymentGatewayGoNoGoService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_WARN = 'WARN';

    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';

    public const DECISION_WATCH = 'WATCH';

    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly PaymentGatewayGovernanceAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $audit = $this->audit->evaluate();

        $signals = [
            $this->commandContractSignal('gateway_commands', (array) config('payment_gateway_governance.gateway_commands', []), 'gateway commands'),
            $this->commandContractSignal('billing_layer_commands', (array) config('payment_gateway_governance.billing_layer_commands', []), 'billing-layer commands'),
            $this->priorGatesSignal(),
            $this->docsSignal(),
            $this->decisionSignal('gateway_governance_audit', (string) $audit['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'gateway_governance_audit' => $audit,
        ];
    }

    private function commandContractSignal(string $key, array $required, string $label): array
    {
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal($key, self::STATUS_PASS, count($required)." {$label} registered.")
            : $this->signal($key, self::STATUS_FAIL, "Missing {$label}: ".implode(', ', $missing));
    }

    private function priorGatesSignal(): array
    {
        $registered = array_keys(Artisan::all());
        $gates = (array) config('payment_gateway_governance.prior_sprint_gates', []);
        $missing = [];
        foreach ($gates as $commands) {
            $missing = array_merge($missing, array_diff((array) $commands, $registered));
        }
        $missing = array_values(array_unique($missing));

        return $missing === []
            ? $this->signal('prior_sprint_gates', self::STATUS_PASS, 'Sprint 24–30 gate commands registered.')
            : $this->signal('prior_sprint_gates', self::STATUS_FAIL, 'Missing prior-sprint gate commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('payment_gateway_governance.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('gateway_docs', self::STATUS_PASS, count($required).' gateway docs present.')
            : $this->signal('gateway_docs', self::STATUS_FAIL, 'Missing gateway docs: '.implode(', ', $missing));
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
        $gates = (array) config('payment_gateway_governance.prior_sprint_gates', []);

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
