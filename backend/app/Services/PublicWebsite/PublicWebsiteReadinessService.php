<?php

namespace App\Services\PublicWebsite;

use App\Models\PublicWebsitePage;
use App\Models\PublicWebsiteSignoff;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 21 — public website readiness evaluation.
 *
 * Aggregates required public pages, landing page content, lead interest
 * governance, SEO metadata, privacy/cookie readiness, package/pricing alignment,
 * public website risk review, and public website signoff review into a secret-safe
 * PASS/WARN/FAIL report and a GO/WATCH/NO_GO decision. Also owns public website
 * page lifecycle (create/update/approve/publish/archive) and signoff recording.
 *
 * NO_GO — a required public page is missing, no approved landing page version, a
 *         missing privacy/terms placeholder, an open CRITICAL/HIGH risk without a
 *         valid accepted risk, or a rejected signoff.
 * WATCH — an open MEDIUM risk with mitigation, an approved-with-risk signoff, a
 *         SEO/content warning, or a package/pricing alignment warning.
 * GO    — every signal passes.
 *
 * Public pages NEVER create tenant/user/subscription/device records. This service
 * never bills, never deploys, and never opens public self-service signup.
 */
class PublicWebsiteReadinessService
{
    use SanitizesPublicWebsiteText;

    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly LandingPageContentService $landing,
        private readonly LeadInterestGovernanceService $leads,
        private readonly SeoMetadataGovernanceService $seo,
        private readonly PrivacyCookieReadinessService $privacy,
        private readonly PublicWebsiteRiskGovernanceService $risks,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $pages = $this->pagesSummary();
        $landing = $this->landing->summary();
        $leads = $this->leads->summary();
        $seo = $this->seo->evaluate();
        $privacy = $this->privacy->evaluate();
        $risk = $this->risks->summary($now);
        $signoff = $this->signoffSummary();

        $signals = [
            $this->decisionSignal('public_pages', (string) $pages['decision']),
            $this->decisionSignal('landing_page', (string) $landing['decision']),
            $this->decisionSignal('lead_governance', (string) $leads['decision']),
            $this->decisionSignal('seo_metadata', (string) $seo['decision']),
            $this->decisionSignal('privacy_cookie', (string) $privacy['decision']),
            $this->decisionSignal('risk_review', (string) $risk['decision']),
            $this->decisionSignal('content_signoff', (string) $signoff['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'public_pages' => $pages,
            'landing_page' => $landing,
            'lead_governance' => $leads,
            'seo_metadata' => $seo,
            'privacy_cookie' => $privacy,
            'risk_review' => $risk,
            'content_signoff' => $signoff,
        ];
    }

    /**
     * Required public pages must exist and be approved or published. A missing
     * required page forces NO_GO; a present-but-not-approved page forces WATCH.
     *
     * @return array<string,mixed>
     */
    public function pagesSummary(): array
    {
        $required = (array) config('public_website.required_pages', []);

        $missing = [];
        $notApproved = [];
        foreach ($required as $key) {
            $page = PublicWebsitePage::query()->where('page_key', $key)->first();
            if ($page === null) {
                $missing[] = $key;

                continue;
            }
            if (! in_array($page->status, [PublicWebsitePage::STATUS_APPROVED, PublicWebsitePage::STATUS_PUBLISHED], true)) {
                $notApproved[] = $key;
            }
        }

        $decision = self::DECISION_GO;
        if ($missing !== []) {
            $decision = self::DECISION_NO_GO;
        } elseif ($notApproved !== []) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'required' => array_values($required),
            'missing' => $missing,
            'not_approved' => $notApproved,
            'counts' => [
                'required' => count($required),
                'approved_or_published' => count($required) - count($missing) - count($notApproved),
            ],
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
        $required = (array) config('public_website.required_signoff_roles', []);

        $signoffs = PublicWebsiteSignoff::query()->get();

        $rejected = $signoffs->filter(fn (PublicWebsiteSignoff $s) => $s->decision === PublicWebsiteSignoff::DECISION_REJECTED)->count();
        $approvedWithRisk = $signoffs->filter(fn (PublicWebsiteSignoff $s) => $s->decision === PublicWebsiteSignoff::DECISION_APPROVED_WITH_RISK)->count();

        $approvingRoles = $signoffs
            ->filter(fn (PublicWebsiteSignoff $s) => in_array($s->decision, [
                PublicWebsiteSignoff::DECISION_APPROVED,
                PublicWebsiteSignoff::DECISION_APPROVED_WITH_RISK,
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
     * @param array<string,mixed> $data
     */
    public function addSignoff(array $data, ?User $actor = null): PublicWebsiteSignoff
    {
        return PublicWebsiteSignoff::query()->create([
            'signoff_reference' => (string) ($data['signoff_reference'] ?? $this->generateSignoffReference()),
            'signer_user_id' => $data['signer_user_id'] ?? $actor?->id,
            'signer_name' => $this->sanitizeNullableString($data['signer_name'] ?? $actor?->name),
            'signer_role' => $this->normalizeSignerRole((string) ($data['signer_role'] ?? '')),
            'decision' => $this->normalizeSignoffDecision((string) ($data['decision'] ?? PublicWebsiteSignoff::DECISION_PENDING)),
            'notes' => $this->sanitizeNullableString($data['notes'] ?? null),
            'evidence_reference' => $data['evidence_reference'] ?? null,
            'signed_at' => Carbon::now(),
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);
    }

    // --- Public website page lifecycle ------------------------------------

    /**
     * @param array<string,mixed> $attributes
     */
    public function createPage(array $attributes, ?User $actor = null): PublicWebsitePage
    {
        return PublicWebsitePage::query()->create([
            'page_key' => $this->normalizePageKey((string) ($attributes['page_key'] ?? '')),
            'slug' => (string) ($attributes['slug'] ?? Str::slug((string) ($attributes['title'] ?? $attributes['page_key'] ?? 'page'))),
            'title' => $this->sanitizeString((string) ($attributes['title'] ?? 'Untitled')),
            'status' => $this->normalizePageStatus((string) ($attributes['status'] ?? PublicWebsitePage::STATUS_DRAFT)),
            'seo_title' => $this->sanitizeNullableString($attributes['seo_title'] ?? null),
            'seo_description' => $this->sanitizeNullableString($attributes['seo_description'] ?? null),
            'content_sections' => $this->sanitizeArray($attributes['content_sections'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function updatePage(PublicWebsitePage $page, array $attributes, ?User $actor = null): PublicWebsitePage
    {
        $map = [
            'slug' => fn ($v) => (string) $v,
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'status' => fn ($v) => $this->normalizePageStatus((string) $v),
            'seo_title' => fn ($v) => $this->sanitizeNullableString($v),
            'seo_description' => fn ($v) => $this->sanitizeNullableString($v),
            'content_sections' => fn ($v) => $this->sanitizeArray($v),
            'evidence_reference' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $page->{$key} = $caster($attributes[$key]);
            }
        }

        $page->save();

        return $page->refresh();
    }

    public function approvePage(PublicWebsitePage $page, ?User $actor = null): PublicWebsitePage
    {
        $page->status = PublicWebsitePage::STATUS_APPROVED;
        $page->approved_by = $actor?->id;
        $page->approved_at = Carbon::now();
        $page->save();

        return $page->refresh();
    }

    public function publishPage(PublicWebsitePage $page, ?User $actor = null): PublicWebsitePage
    {
        $page->status = PublicWebsitePage::STATUS_PUBLISHED;
        $page->published_at = Carbon::now();
        if ($page->approved_at === null) {
            $page->approved_by = $actor?->id;
            $page->approved_at = Carbon::now();
        }
        $page->save();

        return $page->refresh();
    }

    public function archivePage(PublicWebsitePage $page, ?User $actor = null): PublicWebsitePage
    {
        $page->status = PublicWebsitePage::STATUS_ARCHIVED;
        $page->save();

        return $page->refresh();
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

    private function normalizePageKey(string $key): string
    {
        $key = strtoupper(trim($key));
        if (! in_array($key, PublicWebsitePage::KEYS, true)) {
            throw new InvalidArgumentException("Invalid page key: {$key}");
        }

        return $key;
    }

    private function normalizePageStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, PublicWebsitePage::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeSignerRole(string $role): string
    {
        $role = strtoupper(trim($role));
        if (! in_array($role, PublicWebsiteSignoff::ROLES, true)) {
            throw new InvalidArgumentException("Invalid signer role: {$role}");
        }

        return $role;
    }

    private function normalizeSignoffDecision(string $decision): string
    {
        $decision = strtoupper(trim($decision));
        if (! in_array($decision, PublicWebsiteSignoff::DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid signoff decision: {$decision}");
        }

        return $decision;
    }

    private function generateSignoffReference(): string
    {
        return 'WSIGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
