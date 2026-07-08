<?php

namespace App\Services\TenantPlan;

/**
 * Sprint 26 — the immutable result of a feature entitlement resolution.
 *
 * Produced only by FeatureEntitlementService. Carries whether the feature is
 * entitled, the feature key, the resolving plan, the source (plan / override /
 * default) and the stable machine code for a denial (FEATURE_NOT_ENTITLED,
 * TPE-R008). Never carries secrets.
 */
final class EntitlementDecision
{
    public const SOURCE_PLAN = 'plan';
    public const SOURCE_OVERRIDE = 'override';
    public const SOURCE_DEFAULT = 'default';

    public function __construct(
        public readonly bool $entitled,
        public readonly string $feature,
        public readonly string $planKey,
        public readonly string $source,
        public readonly ?string $code = null,
    ) {}

    public function denied(): bool
    {
        return ! $this->entitled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'entitled' => $this->entitled,
            'plan_key' => $this->planKey,
            'source' => $this->source,
            'code' => $this->code,
        ];
    }
}
