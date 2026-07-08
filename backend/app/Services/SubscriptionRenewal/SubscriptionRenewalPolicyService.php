<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalPolicy;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal policy lifecycle.
 *
 * Creates/updates a renewal policy, ensures a default active policy exists, and
 * validates the renewal/grace/dunning windows. A policy is governance metadata
 * only — it NEVER triggers real sending, NEVER auto-charges, and NEVER auto-
 * suspends a tenant. Secret-looking free-text/metadata is stripped.
 */
class SubscriptionRenewalPolicyService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SubscriptionRenewalPolicy
    {
        $windows = $this->validateWindows($attributes);

        return SubscriptionRenewalPolicy::query()->create([
            'policy_reference' => (string) ($attributes['policy_reference'] ?? $this->generateReference()),
            'code' => strtoupper((string) ($attributes['code'] ?? 'POLICY_'.strtoupper(Str::random(6)))),
            'name' => $this->sanitizeString((string) ($attributes['name'] ?? 'Unnamed policy')),
            'description' => $this->sanitizeNullableString($attributes['description'] ?? null),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SubscriptionRenewalPolicy::STATUS_ACTIVE)),
            'renewal_window_days' => $windows['renewal_window_days'],
            'grace_period_days' => $windows['grace_period_days'],
            'dunning_start_days_before_expiry' => $windows['dunning_start_days_before_expiry'],
            'max_manual_dunning_notices' => $windows['max_manual_dunning_notices'],
            'requires_manual_approval' => (bool) ($attributes['requires_manual_approval'] ?? true),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SubscriptionRenewalPolicy $policy, array $attributes, ?User $actor = null): SubscriptionRenewalPolicy
    {
        // Validate any window that is being changed, using existing values as defaults.
        $windowKeys = ['renewal_window_days', 'grace_period_days', 'dunning_start_days_before_expiry', 'max_manual_dunning_notices'];
        if (array_intersect($windowKeys, array_keys($attributes)) !== []) {
            $merged = array_merge($policy->only($windowKeys), $attributes);
            $this->validateWindows($merged);
        }

        $map = [
            'code' => fn ($v) => strtoupper((string) $v),
            'name' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'renewal_window_days' => fn ($v) => max(0, (int) $v),
            'grace_period_days' => fn ($v) => max(0, (int) $v),
            'dunning_start_days_before_expiry' => fn ($v) => max(0, (int) $v),
            'max_manual_dunning_notices' => fn ($v) => max(0, (int) $v),
            'requires_manual_approval' => fn ($v) => (bool) $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $policy->{$key} = $caster($attributes[$key]);
            }
        }

        $policy->save();

        return $policy->refresh();
    }

    public function ensureDefault(?User $actor = null): SubscriptionRenewalPolicy
    {
        $config = (array) config('subscription_renewal.default_policy', []);
        $code = strtoupper((string) ($config['code'] ?? 'DEFAULT_MANUAL_RENEWAL'));

        $existing = SubscriptionRenewalPolicy::query()->where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->create([
            'code' => $code,
            'name' => $config['name'] ?? 'Default Manual Renewal Governance',
            'status' => SubscriptionRenewalPolicy::STATUS_ACTIVE,
            'renewal_window_days' => $config['renewal_window_days'] ?? 14,
            'grace_period_days' => $config['grace_period_days'] ?? 7,
            'dunning_start_days_before_expiry' => $config['dunning_start_days_before_expiry'] ?? 7,
            'max_manual_dunning_notices' => $config['max_manual_dunning_notices'] ?? 3,
            'requires_manual_approval' => $config['requires_manual_approval'] ?? true,
        ], $actor);
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SubscriptionRenewalPolicy::query()->get();
        $config = (array) config('subscription_renewal.default_policy', []);
        $defaultCode = strtoupper((string) ($config['code'] ?? 'DEFAULT_MANUAL_RENEWAL'));

        $defaultActive = $all
            ->where('code', $defaultCode)
            ->where('status', SubscriptionRenewalPolicy::STATUS_ACTIVE)
            ->isNotEmpty();

        return [
            'decision' => $defaultActive ? self::DECISION_GO : 'WATCH',
            'total_policies' => $all->count(),
            'active' => $all->where('status', SubscriptionRenewalPolicy::STATUS_ACTIVE)->count(),
            'default_policy_active' => $defaultActive,
            'auto_charge' => false,
            'auto_suspension' => false,
            'real_sending' => false,
        ];
    }

    /**
     * @param array<string,mixed> $attributes
     * @return array{renewal_window_days:int,grace_period_days:int,dunning_start_days_before_expiry:int,max_manual_dunning_notices:int}
     */
    private function validateWindows(array $attributes): array
    {
        $renewal = (int) ($attributes['renewal_window_days'] ?? 14);
        $grace = (int) ($attributes['grace_period_days'] ?? 7);
        $dunning = (int) ($attributes['dunning_start_days_before_expiry'] ?? 7);
        $maxNotices = (int) ($attributes['max_manual_dunning_notices'] ?? 3);

        if ($renewal < 0 || $grace < 0 || $dunning < 0 || $maxNotices < 0) {
            throw new InvalidArgumentException('Renewal/grace/dunning windows must be non-negative.');
        }

        if ($dunning > $renewal) {
            throw new InvalidArgumentException('Dunning start window cannot exceed the renewal window.');
        }

        if ($maxNotices < 1) {
            throw new InvalidArgumentException('At least one manual dunning notice must be allowed.');
        }

        return [
            'renewal_window_days' => $renewal,
            'grace_period_days' => $grace,
            'dunning_start_days_before_expiry' => $dunning,
            'max_manual_dunning_notices' => $maxNotices,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SubscriptionRenewalPolicy::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid policy status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'SRPOL-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
