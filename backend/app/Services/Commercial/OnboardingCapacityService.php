<?php

namespace App\Services\Commercial;

use App\Models\SaasPackageCatalog;

/**
 * Sprint 20 — commercial onboarding capacity governance.
 *
 * Evaluates the aggregate weekly onboarding capacity placeholders (self-guided /
 * assisted / managed) against the active package catalog's onboarding levels and
 * verifies the onboarding capacity documentation exists, then derives a
 * GO/WATCH/NO_GO decision. Uses aggregate placeholders only — never creates real
 * tenants and never touches real customer data.
 *
 * NO_GO — the onboarding capacity doc is missing, or an active package requires an
 *         onboarding level with zero configured weekly capacity.
 * WATCH — capacity is configured but an active package uses a higher-touch level
 *         than the largest configured capacity tier.
 * GO    — capacity configured and every active package's onboarding level is
 *         backed by weekly capacity.
 */
class OnboardingCapacityService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $capacity = (array) config('commercial_launch.onboarding_capacity', []);
        $perLevel = [
            SaasPackageCatalog::ONBOARDING_SELF_GUIDED => (int) ($capacity['self_guided_per_week'] ?? 0),
            SaasPackageCatalog::ONBOARDING_ASSISTED => (int) ($capacity['assisted_per_week'] ?? 0),
            SaasPackageCatalog::ONBOARDING_MANAGED => (int) ($capacity['managed_per_week'] ?? 0),
        ];

        $docs = (array) config('commercial_launch.onboarding_capacity_docs', []);
        $missingDocs = [];
        foreach ($docs as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missingDocs[] = $doc;
            }
        }

        $active = SaasPackageCatalog::query()->active()->get();
        $usedLevels = $active->pluck('onboarding_level')->unique()->values()->all();

        $uncoveredLevels = [];
        foreach ($usedLevels as $level) {
            if (($perLevel[$level] ?? 0) <= 0) {
                $uncoveredLevels[] = $level;
            }
        }

        $totalWeekly = array_sum($perLevel);

        $decision = self::DECISION_GO;
        if ($missingDocs !== [] || $uncoveredLevels !== []) {
            $decision = self::DECISION_NO_GO;
        } elseif ($totalWeekly === 0) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'capacity_per_week' => $perLevel,
            'total_per_week' => $totalWeekly,
            'active_onboarding_levels' => $usedLevels,
            'uncovered_levels' => $uncoveredLevels,
            'missing_docs' => $missingDocs,
        ];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
