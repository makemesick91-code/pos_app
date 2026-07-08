<?php

namespace App\Services\BillingCollection;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Sprint 23 — billing collection GO / WATCH / NO_GO aggregation.
 *
 * Combines the cumulative prior-sprint gate contract (Sprint 13 release, 14
 * RC/UAT, 15 deployment/field, 16 monitoring/hypercare, 17 stabilization, 18
 * closure/handover, 19 production operations, 20 commercial launch, 21 public
 * website, 22 sales pipeline commands registered), the billing collection
 * documentation contract, the Android release readiness script, and the full
 * billing collection readiness evaluation (guardrails, docs, accounts, cycles,
 * invoice lifecycle, payment evidence, manual collection, risk review, sign-off
 * review) into a single decision.
 *
 *   NO_GO — any required prior gate/command/doc is missing, an automation
 *           guardrail is enabled, an open CRITICAL/HIGH risk without a valid
 *           accepted risk, or a rejected sign-off.
 *   WATCH — no blocking failure but a warning exists (open MEDIUM risk, an
 *           approved-with-risk sign-off, or a missing sign-off role).
 *   GO    — every signal passes.
 *
 * Never prints secrets, never deploys, never charges a tenant, never calls a
 * payment gateway, never auto-suspends a tenant, never auto-renews a subscription,
 * never sends real WhatsApp/email/Slack, never runs Android Gradle.
 */
class BillingCollectionGoNoGoService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly BillingCollectionReadinessService $readiness,
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
            $this->decisionSignal('config_guardrails', (string) $readiness['config_guardrails']['decision']),
            $this->decisionSignal('billing_accounts', (string) $readiness['billing_accounts']['decision']),
            $this->decisionSignal('invoice_lifecycle', (string) $readiness['invoice_lifecycle']['decision']),
            $this->decisionSignal('payment_evidence', (string) $readiness['payment_evidence']['decision']),
            $this->decisionSignal('manual_collection', (string) $readiness['manual_collection']['decision']),
            $this->decisionSignal('risk_governance', (string) $readiness['risk_governance']['decision']),
            $this->decisionSignal('signoff_governance', (string) $readiness['signoff_governance']['decision']),
            $this->decisionSignal('billing_collection_readiness', (string) $readiness['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'gates' => $this->gateReferences(),
            'billing_collection_readiness' => $readiness,
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
        $required = (array) config('billing_collection.required_commands', []);
        $registered = array_keys(Artisan::all());
        $missing = array_values(array_diff($required, $registered));

        return $missing === []
            ? $this->signal('required_commands', self::STATUS_PASS, count($required).' prior-sprint commands registered.')
            : $this->signal('required_commands', self::STATUS_FAIL, 'Missing required commands: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('billing_collection.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('billing_collection_docs', self::STATUS_PASS, count($required).' billing collection docs present.')
            : $this->signal('billing_collection_docs', self::STATUS_FAIL, 'Missing billing collection docs: '.implode(', ', $missing));
    }

    private function androidScriptSignal(): array
    {
        $script = (string) config('billing_collection.android_release_readiness_script', '');
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
        $gates = (array) config('billing_collection.prior_sprint_gates', []);

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
