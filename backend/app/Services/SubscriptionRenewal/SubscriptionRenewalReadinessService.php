<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalSignoff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal & dunning readiness evaluation.
 *
 * Aggregates the automation guardrails, the renewal documentation contract,
 * default policy governance, run/candidate governance, manual-only dunning
 * governance, decision governance, risk review, and sign-off review into a
 * secret-safe PASS/WARN/FAIL report and a GO/WATCH/NO_GO decision. Also owns
 * subscription renewal sign-off recording.
 *
 * NO_GO — a required doc is missing, an automation guardrail is enabled, an open
 *         CRITICAL/HIGH risk without a valid accepted risk, or a rejected sign-off.
 * WATCH — an open MEDIUM risk, an approved-with-risk sign-off, a missing sign-off
 *         role, or a missing default policy.
 * GO    — every signal passes.
 *
 * This service NEVER charges a tenant, NEVER calls a payment gateway, NEVER auto-
 * suspends/reactivates a tenant, NEVER auto-renews a subscription, and NEVER sends
 * a real message.
 */
class SubscriptionRenewalReadinessService
{
    use SanitizesSubscriptionRenewalText;

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** Config flags that must all be false; any true value forces NO_GO. */
    private const GUARDRAIL_FLAGS = [
        'real_payment_gateway_allowed',
        'auto_charge_allowed',
        'subscription_payment_automation_allowed',
        'auto_tenant_suspension_allowed',
        'auto_tenant_reactivation_allowed',
        'auto_subscription_renewal_allowed',
        'auto_plan_change_allowed',
        'auto_device_limit_change_allowed',
        'public_renewal_portal_allowed',
        'public_payment_link_allowed',
        'real_email_sending_allowed',
        'real_whatsapp_sending_allowed',
        'real_sms_sending_allowed',
        'real_alert_sending_allowed',
        'real_crm_integration_allowed',
        'real_accounting_integration_allowed',
    ];

    public function __construct(
        private readonly SubscriptionRenewalPolicyService $policies,
        private readonly SubscriptionRenewalRunService $runs,
        private readonly SubscriptionRenewalCandidateService $candidates,
        private readonly SubscriptionDunningNoticeService $dunning,
        private readonly SubscriptionRenewalRiskGovernanceService $risks,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $guardrails = $this->guardrailsSummary();
        $docs = $this->docsSummary();
        $policy = $this->policies->summary();
        $run = $this->runs->summary();
        $candidate = $this->candidates->summary();
        $dunning = $this->dunning->summary();
        $risk = $this->risks->summary($now);
        $signoff = $this->signoffSummary();

        $signals = [
            $this->decisionSignal('config_guardrails', (string) $guardrails['decision']),
            $this->decisionSignal('docs', (string) $docs['decision']),
            $this->decisionSignal('policy_governance', (string) $policy['decision']),
            $this->decisionSignal('run_candidate_governance', (string) $this->worst([(string) $run['decision'], (string) $candidate['decision']])),
            $this->decisionSignal('dunning_manual_only', (string) $dunning['decision']),
            $this->decisionSignal('decision_governance', self::DECISION_GO),
            $this->decisionSignal('risk_governance', (string) $risk['decision']),
            $this->decisionSignal('signoff_governance', (string) $signoff['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'config_guardrails' => $guardrails,
            'docs' => $docs,
            'policy_governance' => $policy,
            'run_governance' => $run,
            'candidate_governance' => $candidate,
            'dunning_manual_only' => $dunning,
            'risk_governance' => $risk,
            'signoff_governance' => $signoff,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function guardrailsSummary(): array
    {
        $enabled = [];
        foreach (self::GUARDRAIL_FLAGS as $flag) {
            if (config('subscription_renewal.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return [
            'decision' => $enabled === [] ? self::DECISION_GO : self::DECISION_NO_GO,
            'flags' => self::GUARDRAIL_FLAGS,
            'enabled_forbidden_automation' => $enabled,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function signoffSummary(): array
    {
        $required = (array) config('subscription_renewal.required_signoff_roles', []);

        $signoffs = SubscriptionRenewalSignoff::query()->get();

        $rejected = $signoffs->filter(fn (SubscriptionRenewalSignoff $s) => $s->decision === SubscriptionRenewalSignoff::DECISION_REJECTED)->count();
        $approvedWithRisk = $signoffs->filter(fn (SubscriptionRenewalSignoff $s) => $s->decision === SubscriptionRenewalSignoff::DECISION_APPROVED_WITH_RISK)->count();

        $approvingRoles = $signoffs
            ->filter(fn (SubscriptionRenewalSignoff $s) => in_array($s->decision, [
                SubscriptionRenewalSignoff::DECISION_APPROVED,
                SubscriptionRenewalSignoff::DECISION_APPROVED_WITH_RISK,
            ], true))
            ->pluck('signer_role')
            ->unique()
            ->values()
            ->all();

        $missingRoles = array_values(array_diff($required, $approvingRoles));

        $decision = self::DECISION_GO;
        if ($rejected > 0) {
            $decision = self::DECISION_NO_GO;
        } elseif ($approvedWithRisk > 0 || $missingRoles !== []) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'required_roles' => $required,
            'approving_roles' => $approvingRoles,
            'missing_roles' => $missingRoles,
            'rejected' => $rejected,
            'approved_with_risk' => $approvedWithRisk,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function docsSummary(): array
    {
        $required = (array) config('subscription_renewal.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return [
            'decision' => $missing === [] ? self::DECISION_GO : self::DECISION_NO_GO,
            'required' => array_values($required),
            'missing' => $missing,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function addSignoff(array $data, ?User $actor = null): SubscriptionRenewalSignoff
    {
        return SubscriptionRenewalSignoff::query()->create([
            'signoff_reference' => (string) ($data['signoff_reference'] ?? $this->generateSignoffReference()),
            'signer_user_id' => $data['signer_user_id'] ?? $actor?->id,
            'signer_name' => $this->sanitizeNullableString($data['signer_name'] ?? $actor?->name),
            'signer_role' => $this->normalizeSignerRole((string) ($data['signer_role'] ?? '')),
            'decision' => $this->normalizeSignoffDecision((string) ($data['decision'] ?? SubscriptionRenewalSignoff::DECISION_PENDING)),
            'notes' => $this->sanitizeNullableString($data['notes'] ?? null),
            'evidence_reference' => $data['evidence_reference'] ?? null,
            'signed_at' => Carbon::now(),
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);
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

    /**
     * @param array<int,string> $decisions
     */
    private function worst(array $decisions): string
    {
        if (in_array(self::DECISION_NO_GO, $decisions, true)) {
            return self::DECISION_NO_GO;
        }
        if (in_array(self::DECISION_WATCH, $decisions, true)) {
            return self::DECISION_WATCH;
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

    private function normalizeSignerRole(string $role): string
    {
        $role = strtoupper(trim($role));
        if (! in_array($role, SubscriptionRenewalSignoff::ROLES, true)) {
            throw new InvalidArgumentException("Invalid signer role: {$role}");
        }

        return $role;
    }

    private function normalizeSignoffDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, SubscriptionRenewalSignoff::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid signoff decision: {$decision}");
        }

        return $decision;
    }

    private function generateSignoffReference(): string
    {
        return 'SRSIGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
