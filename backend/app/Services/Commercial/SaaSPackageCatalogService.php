<?php

namespace App\Services\Commercial;

use App\Models\SaasPackageCatalog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 20 — SaaS package catalog lifecycle.
 *
 * Owns create/update/approve/retire for SaaS package catalog entries and
 * summarizes active/draft/blocked packages plus segment coverage into a
 * GO/WATCH/NO_GO decision. Package pricing is governance metadata only — it never
 * activates real billing, never opens public signup, and never mutates tenant
 * subscriptions or bypasses device limits. Secret-looking values are stripped from
 * free-text/metadata so the catalog can never leak credentials.
 */
class SaaSPackageCatalogService
{
    use SanitizesCommercialText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SaasPackageCatalog
    {
        return SaasPackageCatalog::query()->create([
            'package_code' => (string) ($attributes['package_code'] ?? $this->generateCode()),
            'name' => $this->sanitizeString((string) ($attributes['name'] ?? 'Untitled package')),
            'target_segment' => $this->normalizeSegment((string) ($attributes['target_segment'] ?? SaasPackageCatalog::SEGMENT_GENERAL_UMKM)),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SaasPackageCatalog::STATUS_DRAFT)),
            'monthly_price' => $this->normalizeLimit($attributes['monthly_price'] ?? null),
            'currency' => strtoupper((string) ($attributes['currency'] ?? 'IDR')),
            'device_limit' => $this->normalizeLimit($attributes['device_limit'] ?? null),
            'store_limit' => $this->normalizeLimit($attributes['store_limit'] ?? null),
            'user_limit' => $this->normalizeLimit($attributes['user_limit'] ?? null),
            'onboarding_level' => $this->normalizeOnboarding((string) ($attributes['onboarding_level'] ?? SaasPackageCatalog::ONBOARDING_SELF_GUIDED)),
            'support_level' => $this->normalizeSupport((string) ($attributes['support_level'] ?? SaasPackageCatalog::SUPPORT_BASIC)),
            'feature_flags' => $this->sanitizeArray($attributes['feature_flags'] ?? null),
            'included_modules' => $attributes['included_modules'] ?? null,
            'excluded_modules' => $attributes['excluded_modules'] ?? null,
            'commercial_notes' => $this->sanitizeNullableString($attributes['commercial_notes'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SaasPackageCatalog $package, array $attributes, ?User $actor = null): SaasPackageCatalog
    {
        $map = [
            'name' => fn ($v) => $this->sanitizeString((string) $v),
            'target_segment' => fn ($v) => $this->normalizeSegment((string) $v),
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'monthly_price' => fn ($v) => $this->normalizeLimit($v),
            'currency' => fn ($v) => strtoupper((string) $v),
            'device_limit' => fn ($v) => $this->normalizeLimit($v),
            'store_limit' => fn ($v) => $this->normalizeLimit($v),
            'user_limit' => fn ($v) => $this->normalizeLimit($v),
            'onboarding_level' => fn ($v) => $this->normalizeOnboarding((string) $v),
            'support_level' => fn ($v) => $this->normalizeSupport((string) $v),
            'feature_flags' => fn ($v) => $this->sanitizeArray($v),
            'included_modules' => fn ($v) => $v,
            'excluded_modules' => fn ($v) => $v,
            'commercial_notes' => fn ($v) => $this->sanitizeNullableString($v),
            'evidence_reference' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $package->{$key} = $caster($attributes[$key]);
            }
        }

        $package->save();

        return $package->refresh();
    }

    public function approve(SaasPackageCatalog $package, ?User $actor = null): SaasPackageCatalog
    {
        $package->status = SaasPackageCatalog::STATUS_ACTIVE;
        $package->approved_by = $actor?->id;
        $package->approved_at = Carbon::now();
        $package->save();

        return $package->refresh();
    }

    public function retire(SaasPackageCatalog $package, ?User $actor = null): SaasPackageCatalog
    {
        $package->status = SaasPackageCatalog::STATUS_RETIRED;
        $package->save();

        return $package->refresh();
    }

    /**
     * Summarize the catalog and derive a GO/WATCH/NO_GO decision.
     *
     * NO_GO — no active package, or a required segment is uncovered.
     * WATCH — a recommended segment is uncovered, or a package is BLOCKED.
     * GO    — at least one active package covering all required segments.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasPackageCatalog::query()->get();

        $counts = [];
        foreach (SaasPackageCatalog::STATUSES as $status) {
            $counts[$status] = $all->where('status', $status)->count();
        }

        $active = $all->where('status', SaasPackageCatalog::STATUS_ACTIVE);
        $activeSegments = $active->pluck('target_segment')->unique()->values()->all();

        $required = (array) config('commercial_launch.required_package_segments', []);
        $recommended = (array) config('commercial_launch.recommended_package_segments', []);
        $missingRequired = array_values(array_diff($required, $activeSegments));
        $missingRecommended = array_values(array_diff($recommended, $activeSegments));

        $decision = self::DECISION_GO;
        if ($active->count() === 0 || $missingRequired !== []) {
            $decision = self::DECISION_NO_GO;
        } elseif ($missingRecommended !== [] || $counts[SaasPackageCatalog::STATUS_BLOCKED] > 0) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'counts' => $counts,
            'active_total' => $active->count(),
            'active_segments' => $activeSegments,
            'missing_required_segments' => $missingRequired,
            'missing_recommended_segments' => $missingRecommended,
        ];
    }

    private function normalizeSegment(string $segment): string
    {
        $segment = strtoupper(trim($segment));
        if (! in_array($segment, SaasPackageCatalog::SEGMENTS, true)) {
            throw new InvalidArgumentException("Invalid target segment: {$segment}");
        }

        return $segment;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SaasPackageCatalog::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid package status: {$status}");
        }

        return $status;
    }

    private function normalizeOnboarding(string $level): string
    {
        $level = strtoupper(trim($level));

        return in_array($level, SaasPackageCatalog::ONBOARDING_LEVELS, true)
            ? $level
            : SaasPackageCatalog::ONBOARDING_SELF_GUIDED;
    }

    private function normalizeSupport(string $level): string
    {
        $level = strtoupper(trim($level));

        return in_array($level, SaasPackageCatalog::SUPPORT_LEVELS, true)
            ? $level
            : SaasPackageCatalog::SUPPORT_BASIC;
    }

    private function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException('Limit/price must not be negative.');
        }

        return $int;
    }

    private function generateCode(): string
    {
        return 'PKG-'.strtoupper(Str::random(8));
    }
}
