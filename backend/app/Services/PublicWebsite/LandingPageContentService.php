<?php

namespace App\Services\PublicWebsite;

use App\Models\LandingPageVersion;
use App\Models\SaasPackageCatalog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 21 — landing page content lifecycle.
 *
 * Owns create/update/approve/publish/archive for landing page versions and
 * summarizes landing content into a GO/WATCH/NO_GO decision. A landing CTA target
 * must be one of the allowed interest-only targets (never account creation).
 * Package highlights are validated against the active commercial package catalog.
 * Secret-looking values (and live tracking tokens) are stripped from all
 * free-text/metadata so a landing version can never leak credentials. This
 * service never bills, never deploys, and never opens public self-service signup.
 */
class LandingPageContentService
{
    use SanitizesPublicWebsiteText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): LandingPageVersion
    {
        $this->assertCtaAllowed($attributes['hero_cta_target'] ?? null);

        return LandingPageVersion::query()->create([
            'version_reference' => (string) ($attributes['version_reference'] ?? $this->generateReference()),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? LandingPageVersion::STATUS_DRAFT)),
            'headline' => $this->sanitizeString((string) ($attributes['headline'] ?? 'Aish POS Lite')),
            'subheadline' => $this->sanitizeNullableString($attributes['subheadline'] ?? null),
            'hero_cta_label' => $this->sanitizeNullableString($attributes['hero_cta_label'] ?? null),
            'hero_cta_target' => $attributes['hero_cta_target'] ?? null,
            'target_segments' => $this->sanitizeArray($attributes['target_segments'] ?? null),
            'package_highlights' => $this->sanitizeArray($attributes['package_highlights'] ?? null),
            'feature_highlights' => $this->sanitizeArray($attributes['feature_highlights'] ?? null),
            'proof_points' => $this->sanitizeArray($attributes['proof_points'] ?? null),
            'faq_items' => $this->sanitizeArray($attributes['faq_items'] ?? null),
            'seo_summary' => $this->sanitizeArray($attributes['seo_summary'] ?? null),
            'privacy_summary' => $this->sanitizeArray($attributes['privacy_summary'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(LandingPageVersion $version, array $attributes, ?User $actor = null): LandingPageVersion
    {
        if (array_key_exists('hero_cta_target', $attributes)) {
            $this->assertCtaAllowed($attributes['hero_cta_target']);
        }

        $map = [
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'headline' => fn ($v) => $this->sanitizeString((string) $v),
            'subheadline' => fn ($v) => $this->sanitizeNullableString($v),
            'hero_cta_label' => fn ($v) => $this->sanitizeNullableString($v),
            'hero_cta_target' => fn ($v) => $v,
            'target_segments' => fn ($v) => $this->sanitizeArray($v),
            'package_highlights' => fn ($v) => $this->sanitizeArray($v),
            'feature_highlights' => fn ($v) => $this->sanitizeArray($v),
            'proof_points' => fn ($v) => $this->sanitizeArray($v),
            'faq_items' => fn ($v) => $this->sanitizeArray($v),
            'seo_summary' => fn ($v) => $this->sanitizeArray($v),
            'privacy_summary' => fn ($v) => $this->sanitizeArray($v),
            'evidence_reference' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $version->{$key} = $caster($attributes[$key]);
            }
        }

        $version->save();

        return $version->refresh();
    }

    public function approve(LandingPageVersion $version, ?User $actor = null): LandingPageVersion
    {
        $version->status = LandingPageVersion::STATUS_APPROVED;
        $version->approved_by = $actor?->id;
        $version->approved_at = Carbon::now();
        $version->save();

        return $version->refresh();
    }

    public function publish(LandingPageVersion $version, ?User $actor = null): LandingPageVersion
    {
        // Publishing a version supersedes any previously published version.
        LandingPageVersion::query()
            ->where('id', '!=', $version->id)
            ->where('status', LandingPageVersion::STATUS_PUBLISHED)
            ->update(['status' => LandingPageVersion::STATUS_ARCHIVED]);

        $version->status = LandingPageVersion::STATUS_PUBLISHED;
        $version->published_at = Carbon::now();
        if ($version->approved_at === null) {
            $version->approved_by = $actor?->id;
            $version->approved_at = Carbon::now();
        }
        $version->save();

        return $version->refresh();
    }

    public function archive(LandingPageVersion $version, ?User $actor = null): LandingPageVersion
    {
        $version->status = LandingPageVersion::STATUS_ARCHIVED;
        $version->save();

        return $version->refresh();
    }

    /**
     * Summarize landing content readiness into a GO/WATCH/NO_GO decision.
     *
     * NO_GO — no approved or published landing page version exists.
     * WATCH — a published/approved version exists but its package highlights do
     *         not align with any active commercial package.
     * GO    — a published/approved version exists and package highlights align.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $published = LandingPageVersion::query()->published()->latest('id')->first();
        $approvedOrPublished = LandingPageVersion::query()->approvedOrPublished()->latest('id')->first();
        $active = $approvedOrPublished ?? $published;

        $decision = self::DECISION_GO;
        $warnings = [];

        if ($active === null) {
            $decision = self::DECISION_NO_GO;
            $warnings[] = 'No approved or published landing page version exists.';
        } else {
            $alignment = $this->packageAlignment($active);
            if (! $alignment['aligned']) {
                $decision = self::DECISION_WATCH;
                $warnings = array_merge($warnings, $alignment['warnings']);
            }
        }

        return [
            'decision' => $decision,
            'counts' => [
                'total' => LandingPageVersion::query()->count(),
                'published' => LandingPageVersion::query()->published()->count(),
                'approved_or_published' => LandingPageVersion::query()->approvedOrPublished()->count(),
            ],
            'active_version' => $active === null ? null : [
                'reference' => $active->version_reference,
                'status' => $active->status,
                'headline' => $active->headline,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate that a landing version's package highlights are covered by active
     * commercial packages (by package_code or target_segment).
     *
     * @return array{aligned:bool,warnings:array<int,string>}
     */
    public function packageAlignment(LandingPageVersion $version): array
    {
        $highlights = (array) ($version->package_highlights ?? []);
        if ($highlights === []) {
            return ['aligned' => true, 'warnings' => []];
        }

        $activeCodes = SaasPackageCatalog::query()->active()->pluck('package_code')->filter()->map(
            fn ($c) => strtoupper((string) $c),
        )->all();
        $activeSegments = SaasPackageCatalog::query()->active()->pluck('target_segment')->filter()->map(
            fn ($s) => strtoupper((string) $s),
        )->all();

        if ($activeCodes === [] && $activeSegments === []) {
            return ['aligned' => false, 'warnings' => ['Landing page highlights packages but no active commercial package exists.']];
        }

        $warnings = [];
        foreach ($highlights as $highlight) {
            $ref = strtoupper((string) (is_array($highlight) ? ($highlight['code'] ?? $highlight['segment'] ?? '') : $highlight));
            if ($ref === '') {
                continue;
            }
            if (! in_array($ref, $activeCodes, true) && ! in_array($ref, $activeSegments, true)) {
                $warnings[] = "Landing highlight '{$ref}' does not match any active commercial package.";
            }
        }

        return ['aligned' => $warnings === [], 'warnings' => $warnings];
    }

    private function assertCtaAllowed(mixed $target): void
    {
        if ($target === null || $target === '') {
            return;
        }

        $allowed = (array) config('public_website.allowed_cta_targets', []);
        if (! in_array((string) $target, $allowed, true)) {
            throw new InvalidArgumentException("CTA target not allowed: {$target}. CTA must point to the interest form only.");
        }
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, LandingPageVersion::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'LANDING-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
