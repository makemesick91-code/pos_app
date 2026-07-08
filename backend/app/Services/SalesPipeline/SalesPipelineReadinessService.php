<?php

namespace App\Services\SalesPipeline;

use App\Models\SalesPipelineSignoff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 22 — sales pipeline readiness evaluation.
 *
 * Aggregates canonical stages, lead intake, assignment governance, activity
 * tracking, qualification readiness, onboarding handover readiness, risk review,
 * signoff review, and the sales pipeline documentation contract into a secret-safe
 * PASS/WARN/FAIL report and a GO/WATCH/NO_GO decision. Also owns sales pipeline
 * signoff recording.
 *
 * NO_GO — a canonical stage is missing, a required doc is missing, an open
 *         CRITICAL/HIGH risk without a valid accepted risk, or a rejected signoff.
 * WATCH — an open MEDIUM risk, an approved-with-risk signoff, or a missing signoff
 *         role.
 * GO    — every signal passes.
 *
 * Sales leads NEVER create tenant/user/subscription/device records. This service
 * never bills, never deploys, never integrates a real CRM, and never sends real
 * WhatsApp/email/Slack messages.
 */
class SalesPipelineReadinessService
{
    use SanitizesSalesPipelineText;

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly SalesPipelineStageService $stages,
        private readonly SalesLeadIntakeService $leads,
        private readonly SalesLeadActivityService $activities,
        private readonly SalesLeadAssignmentService $assignments,
        private readonly SalesQualificationService $qualification,
        private readonly SalesPipelineRiskGovernanceService $risks,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $stages = $this->stages->summary();
        $leads = $this->leads->summary();
        $assignments = $this->assignments->summary();
        $activities = $this->activities->summary();
        $qualification = $this->qualification->summary();
        $onboarding = $this->onboardingHandoverSummary();
        $risk = $this->risks->summary($now);
        $signoff = $this->signoffSummary();
        $docs = $this->docsSummary();

        $signals = [
            $this->decisionSignal('canonical_stages', (string) $stages['decision']),
            $this->decisionSignal('lead_intake', (string) $leads['decision']),
            $this->decisionSignal('assignment_governance', (string) $assignments['decision']),
            $this->decisionSignal('activity_tracking', (string) $activities['decision']),
            $this->decisionSignal('qualification', (string) $qualification['decision']),
            $this->decisionSignal('onboarding_handover_readiness', (string) $onboarding['decision']),
            $this->decisionSignal('risk_governance', (string) $risk['decision']),
            $this->decisionSignal('signoff_governance', (string) $signoff['decision']),
            $this->decisionSignal('sales_pipeline_docs', (string) $docs['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'canonical_stages' => $stages,
            'lead_intake' => $leads,
            'assignment_governance' => $assignments,
            'activity_tracking' => $activities,
            'qualification' => $qualification,
            'onboarding_handover_readiness' => $onboarding,
            'risk_governance' => $risk,
            'signoff_governance' => $signoff,
            'sales_pipeline_docs' => $docs,
        ];
    }

    /**
     * Onboarding handover readiness is manual-review only — a ready lead means a
     * manual onboarding review is due, never automatic provisioning.
     *
     * @return array<string,mixed>
     */
    public function onboardingHandoverSummary(): array
    {
        return [
            'decision' => self::DECISION_GO,
            'ready_for_onboarding' => \App\Models\SalesLead::query()->whereNotNull('ready_for_onboarding_at')->count(),
            'auto_provisioning' => false,
            'manual_review_required' => true,
        ];
    }

    /**
     * Signoff readiness across the required roles. A rejected signoff forces
     * NO_GO; an approved-with-risk signoff or a missing required role forces WATCH.
     *
     * @return array<string,mixed>
     */
    public function signoffSummary(): array
    {
        $required = (array) config('sales_pipeline.required_signoff_roles', []);

        $signoffs = SalesPipelineSignoff::query()->get();

        $rejected = $signoffs->filter(fn (SalesPipelineSignoff $s) => $s->decision === SalesPipelineSignoff::DECISION_REJECTED)->count();
        $approvedWithRisk = $signoffs->filter(fn (SalesPipelineSignoff $s) => $s->decision === SalesPipelineSignoff::DECISION_APPROVED_WITH_RISK)->count();

        $approvingRoles = $signoffs
            ->filter(fn (SalesPipelineSignoff $s) => in_array($s->decision, [
                SalesPipelineSignoff::DECISION_APPROVED,
                SalesPipelineSignoff::DECISION_APPROVED_WITH_RISK,
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
        $required = (array) config('sales_pipeline.required_docs', []);
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
    public function addSignoff(array $data, ?User $actor = null): SalesPipelineSignoff
    {
        return SalesPipelineSignoff::query()->create([
            'signoff_reference' => (string) ($data['signoff_reference'] ?? $this->generateSignoffReference()),
            'signer_user_id' => $data['signer_user_id'] ?? $actor?->id,
            'signer_name' => $this->sanitizeNullableString($data['signer_name'] ?? $actor?->name),
            'signer_role' => $this->normalizeSignerRole((string) ($data['signer_role'] ?? '')),
            'decision' => $this->normalizeSignoffDecision((string) ($data['decision'] ?? SalesPipelineSignoff::DECISION_PENDING)),
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
        if (! in_array($role, SalesPipelineSignoff::ROLES, true)) {
            throw new InvalidArgumentException("Invalid signer role: {$role}");
        }

        return $role;
    }

    private function normalizeSignoffDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, SalesPipelineSignoff::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid signoff decision: {$decision}");
        }

        return $decision;
    }

    private function generateSignoffReference(): string
    {
        return 'SPSIGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
