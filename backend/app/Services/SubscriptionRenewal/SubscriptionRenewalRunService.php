<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalPolicy;
use App\Models\SubscriptionRenewalRun;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal run lifecycle.
 *
 * Creates a renewal run and evaluates existing TenantSubscription records into
 * renewal candidates, calculating days_until_expiry, the renewal stage and the
 * priority using the policy's renewal/grace windows. A run links a candidate to a
 * SaaS billing invoice/account ONLY as read-only awareness.
 *
 * A run NEVER renews a subscription, NEVER charges a tenant, NEVER changes a plan
 * or device limit, and NEVER suspends/reactivates a tenant.
 */
class SubscriptionRenewalRunService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';

    public function __construct(
        private readonly SubscriptionRenewalPolicyService $policies,
    ) {}

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SubscriptionRenewalRun
    {
        return SubscriptionRenewalRun::query()->create([
            'run_reference' => (string) ($attributes['run_reference'] ?? $this->generateReference()),
            'policy_id' => $attributes['policy_id'] ?? null,
            'status' => SubscriptionRenewalRun::STATUS_DRAFT,
            'run_date' => isset($attributes['run_date']) ? Carbon::parse($attributes['run_date'])->toDateString() : Carbon::now()->toDateString(),
            'period_start' => isset($attributes['period_start']) ? Carbon::parse($attributes['period_start'])->toDateString() : null,
            'period_end' => isset($attributes['period_end']) ? Carbon::parse($attributes['period_end'])->toDateString() : null,
            'created_by_user_id' => $attributes['created_by_user_id'] ?? $actor?->id,
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * Evaluate existing TenantSubscription records into renewal candidates. This
     * is read-only awareness of subscriptions and billing — it NEVER renews,
     * charges, or suspends.
     */
    public function evaluate(SubscriptionRenewalRun $run, ?User $actor = null, ?Carbon $now = null): SubscriptionRenewalRun
    {
        $now ??= Carbon::now();
        $policy = $this->resolvePolicy($run);

        $run->status = SubscriptionRenewalRun::STATUS_RUNNING;
        $run->started_at = $now;
        $run->save();

        $created = 0;
        $byStage = [];

        TenantSubscription::query()
            ->whereNotIn('status', [TenantSubscription::STATUS_CANCELLED])
            ->with('tenant')
            ->chunkById(200, function ($subscriptions) use ($run, $policy, $now, &$created, &$byStage) {
                foreach ($subscriptions as $subscription) {
                    $candidate = $this->buildCandidate($run, $policy, $subscription, $now);
                    $created++;
                    $byStage[$candidate->renewal_stage] = ($byStage[$candidate->renewal_stage] ?? 0) + 1;
                }
            });

        $summary = [
            'candidates_created' => $created,
            'by_stage' => $byStage,
            'auto_renewed' => 0,
            'auto_charged' => 0,
            'auto_suspended' => 0,
        ];

        $run->status = SubscriptionRenewalRun::STATUS_COMPLETED;
        $run->completed_at = Carbon::now();
        $run->summary = $summary;
        $run->period_start ??= $now->copy()->startOfMonth()->toDateString();
        $run->period_end ??= $now->copy()->endOfMonth()->toDateString();
        $run->save();

        return $run->refresh();
    }

    public function complete(SubscriptionRenewalRun $run, ?User $actor = null): SubscriptionRenewalRun
    {
        if ($run->status === SubscriptionRenewalRun::STATUS_DRAFT) {
            $run->started_at ??= Carbon::now();
        }
        $run->status = SubscriptionRenewalRun::STATUS_COMPLETED;
        $run->completed_at = Carbon::now();
        $run->save();

        return $run->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SubscriptionRenewalRun::query()->get();

        $byStatus = [];
        foreach (SubscriptionRenewalRun::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_runs' => $all->count(),
            'by_status' => $byStatus,
            'auto_renewal' => false,
            'auto_charge' => false,
        ];
    }

    private function buildCandidate(
        SubscriptionRenewalRun $run,
        SubscriptionRenewalPolicy $policy,
        TenantSubscription $subscription,
        Carbon $now,
    ): SubscriptionRenewalCandidate {
        $endsAt = $subscription->ends_at;
        $daysUntilExpiry = $endsAt !== null ? (int) round($now->diffInDays($endsAt, false)) : null;
        $stage = $this->stageFor($daysUntilExpiry, $policy);
        $graceEndsAt = $subscription->grace_ends_at
            ?? ($endsAt !== null ? $endsAt->copy()->addDays($policy->grace_period_days) : null);

        return SubscriptionRenewalCandidate::query()->create([
            'candidate_reference' => $this->generateCandidateReference(),
            'run_id' => $run->id,
            'tenant_id' => $subscription->tenant_id,
            'tenant_subscription_id' => $subscription->id,
            'policy_id' => $policy->id,
            'status' => SubscriptionRenewalCandidate::STATUS_NEW,
            'renewal_stage' => $stage,
            'current_subscription_status' => $subscription->status,
            'current_period_start' => optional($subscription->starts_at)->toDateString(),
            'current_period_end' => optional($endsAt)->toDateString(),
            'days_until_expiry' => $daysUntilExpiry,
            'grace_ends_at' => $graceEndsAt,
            'priority' => $this->priorityFor($stage),
            'metadata' => ['evaluated_at' => $now->toIso8601String()],
        ]);
    }

    private function stageFor(?int $daysUntilExpiry, SubscriptionRenewalPolicy $policy): string
    {
        if ($daysUntilExpiry === null) {
            return SubscriptionRenewalCandidate::STAGE_NOT_DUE;
        }

        if ($daysUntilExpiry > $policy->renewal_window_days) {
            return SubscriptionRenewalCandidate::STAGE_NOT_DUE;
        }

        if ($daysUntilExpiry >= 0) {
            return SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW;
        }

        // Expired. Within grace window?
        if (abs($daysUntilExpiry) <= $policy->grace_period_days) {
            return SubscriptionRenewalCandidate::STAGE_GRACE_PERIOD;
        }

        return SubscriptionRenewalCandidate::STAGE_OVERDUE;
    }

    private function priorityFor(string $stage): string
    {
        return match ($stage) {
            SubscriptionRenewalCandidate::STAGE_OVERDUE => SubscriptionRenewalCandidate::PRIORITY_URGENT,
            SubscriptionRenewalCandidate::STAGE_GRACE_PERIOD => SubscriptionRenewalCandidate::PRIORITY_HIGH,
            SubscriptionRenewalCandidate::STAGE_RENEWAL_WINDOW => SubscriptionRenewalCandidate::PRIORITY_NORMAL,
            default => SubscriptionRenewalCandidate::PRIORITY_LOW,
        };
    }

    private function resolvePolicy(SubscriptionRenewalRun $run): SubscriptionRenewalPolicy
    {
        if ($run->policy_id !== null) {
            $policy = SubscriptionRenewalPolicy::query()->find($run->policy_id);
            if ($policy !== null) {
                return $policy;
            }
        }

        return $this->policies->ensureDefault();
    }

    private function generateReference(): string
    {
        return 'SRRUN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function generateCandidateReference(): string
    {
        return 'SRCAND-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }
}
