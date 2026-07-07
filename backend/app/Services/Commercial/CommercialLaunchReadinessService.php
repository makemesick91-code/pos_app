<?php

namespace App\Services\Commercial;

use App\Models\CommercialLaunchRun;
use App\Models\CommercialLaunchSignoff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 20 — commercial launch readiness evaluation.
 *
 * Aggregates package catalog readiness, pricing/plan governance, sales enablement
 * readiness, onboarding capacity, commercial risk review, launch signoff review,
 * and the commercial documentation contract into a secret-safe PASS/WARN/FAIL
 * report and a GO/WATCH/NO_GO decision. Also owns commercial launch run creation /
 * approve / block and signoff recording.
 *
 * NO_GO — no active package, a blocking pricing/onboarding issue, an open
 *         CRITICAL/HIGH risk without a valid accepted risk, a rejected signoff, or
 *         a missing required commercial doc.
 * WATCH — an open MEDIUM risk, an approved-with-risk signoff, a recommended
 *         package/pricing/onboarding warning, or a missing required signoff role.
 * GO    — every signal passes.
 *
 * Recording a run never deploys, never bills a real customer, never opens public
 * signup, and never sends real alerts.
 */
class CommercialLaunchReadinessService
{
    use SanitizesCommercialText;

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly SaaSPackageCatalogService $packages,
        private readonly PricingPlanGovernanceService $pricing,
        private readonly SalesEnablementReadinessService $salesEnablement,
        private readonly OnboardingCapacityService $onboarding,
        private readonly CommercialRiskGovernanceService $risks,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $package = $this->packages->summary();
        $pricing = $this->pricing->evaluate();
        $salesEnablement = $this->salesEnablement->evaluate();
        $onboarding = $this->onboarding->evaluate();
        $risk = $this->risks->summary($now);
        $signoff = $this->signoffSummary();

        $signals = [
            $this->decisionSignal('package_catalog', (string) $package['decision']),
            $this->decisionSignal('pricing_governance', (string) $pricing['decision']),
            $this->decisionSignal('sales_enablement', (string) $salesEnablement['decision']),
            $this->decisionSignal('onboarding_capacity', (string) $onboarding['decision']),
            $this->decisionSignal('risk_review', (string) $risk['decision']),
            $this->decisionSignal('launch_signoff', (string) $signoff['decision']),
            $this->docsSignal(),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'package_catalog' => $package,
            'pricing_governance' => $pricing,
            'sales_enablement' => $salesEnablement,
            'onboarding_capacity' => $onboarding,
            'risk_review' => $risk,
            'launch_signoff' => $signoff,
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
        $required = (array) config('commercial_launch.required_signoff_roles', []);
        $blocking = (array) config('commercial_launch.blocking_signoff_decisions', []);
        $watch = (array) config('commercial_launch.watch_signoff_decisions', []);

        $signoffs = CommercialLaunchSignoff::query()->get();

        $rejected = $signoffs->filter(fn (CommercialLaunchSignoff $s) => in_array($s->decision, $blocking, true))->count();
        $approvedWithRisk = $signoffs->filter(fn (CommercialLaunchSignoff $s) => in_array($s->decision, $watch, true))->count();

        $approvingRoles = $signoffs
            ->filter(fn (CommercialLaunchSignoff $s) => in_array($s->decision, [
                CommercialLaunchSignoff::DECISION_APPROVED,
                CommercialLaunchSignoff::DECISION_APPROVED_WITH_RISK,
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
     * Record a signoff for a launch run.
     *
     * @param array<string,mixed> $data
     */
    public function addSignoff(CommercialLaunchRun $run, array $data, ?User $actor = null): CommercialLaunchSignoff
    {
        return $run->signoffs()->create([
            'signoff_reference' => (string) ($data['signoff_reference'] ?? $this->generateSignoffReference()),
            'signer_user_id' => $data['signer_user_id'] ?? $actor?->id,
            'signer_name' => $this->sanitizeNullableString($data['signer_name'] ?? $actor?->name),
            'signer_role' => $this->normalizeSignerRole((string) ($data['signer_role'] ?? '')),
            'decision' => $this->normalizeSignoffDecision((string) ($data['decision'] ?? CommercialLaunchSignoff::DECISION_PENDING)),
            'notes' => $this->sanitizeNullableString($data['notes'] ?? null),
            'evidence_reference' => $data['evidence_reference'] ?? null,
            'signed_at' => Carbon::now(),
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);
    }

    /**
     * Persist a commercial launch run from the current readiness evaluation.
     *
     * @param array<string,mixed> $attributes
     */
    public function createRun(array $attributes, ?User $actor = null, ?Carbon $now = null): CommercialLaunchRun
    {
        $report = $this->evaluate($now);

        return CommercialLaunchRun::query()->create([
            'launch_reference' => (string) ($attributes['launch_reference'] ?? $this->generateReference()),
            'status' => CommercialLaunchRun::STATUS_REVIEW,
            'decision' => (string) $report['decision'],
            'window_start' => $attributes['window_start'] ?? null,
            'window_end' => $attributes['window_end'] ?? null,
            'package_summary' => $report['package_catalog'],
            'pricing_summary' => $report['pricing_governance'],
            'sales_enablement_summary' => $report['sales_enablement'],
            'onboarding_capacity_summary' => $report['onboarding_capacity'],
            'risk_summary' => $report['risk_review'],
            'signoff_summary' => $report['launch_signoff'],
            'evidence_references' => $attributes['evidence_references'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    public function approve(CommercialLaunchRun $run, ?User $actor = null): CommercialLaunchRun
    {
        $run->status = match ($run->decision) {
            CommercialLaunchRun::DECISION_NO_GO => CommercialLaunchRun::STATUS_BLOCKED,
            CommercialLaunchRun::DECISION_WATCH => CommercialLaunchRun::STATUS_WATCH,
            default => CommercialLaunchRun::STATUS_READY,
        };
        $run->approved_by = $actor?->id;
        $run->approved_at = Carbon::now();
        $run->save();

        return $run->refresh();
    }

    public function block(CommercialLaunchRun $run, ?User $actor = null): CommercialLaunchRun
    {
        $run->status = CommercialLaunchRun::STATUS_BLOCKED;
        $run->save();

        return $run->refresh();
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

    private function normalizeSignerRole(string $role): string
    {
        $role = strtoupper(trim($role));
        if (! in_array($role, CommercialLaunchSignoff::ROLES, true)) {
            throw new InvalidArgumentException("Invalid signer role: {$role}");
        }

        return $role;
    }

    private function normalizeSignoffDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, CommercialLaunchSignoff::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid signoff decision: {$decision}");
        }

        return $decision;
    }

    private function generateReference(): string
    {
        return 'LAUNCH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function generateSignoffReference(): string
    {
        return 'SIGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
