<?php

namespace App\Services\PublicWebsite;

use App\Models\PublicWebsitePage;

/**
 * Sprint 21 — privacy / cookie / terms readiness.
 *
 * Verifies that the privacy page, terms page, cookie/analytics policy placeholder,
 * and lead consent policy readiness exist. In Sprint 21 these are readiness
 * placeholders (clearly marked as templates, not legal finality) and NO live
 * analytics token is permitted. Produces a GO/WATCH/NO_GO decision.
 */
class PrivacyCookieReadinessService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $privacy = $this->pagePresent(PublicWebsitePage::KEY_PRIVACY);
        $terms = $this->pagePresent(PublicWebsitePage::KEY_TERMS);

        $cookieDoc = $this->docPresent((string) config('public_website.cookie_policy_doc', 'docs/public-website/privacy-cookie-readiness.md'));
        $leadPolicyDoc = $this->docPresent((string) config('public_website.lead_policy_doc', 'docs/public-website/lead-interest-policy.md'));

        $signals = [
            'privacy_page' => $privacy,
            'terms_page' => $terms,
            'cookie_policy_placeholder' => $cookieDoc,
            'lead_consent_policy' => $leadPolicyDoc,
        ];

        // Missing privacy/terms pages are blocking; a missing placeholder doc is WATCH.
        $decision = self::DECISION_GO;
        if (! $privacy || ! $terms) {
            $decision = self::DECISION_NO_GO;
        } elseif (! $cookieDoc || ! $leadPolicyDoc) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'signals' => $signals,
            'live_analytics_token_allowed' => (bool) config('public_website.live_tracking_tokens_allowed', false),
        ];
    }

    private function pagePresent(string $key): bool
    {
        return PublicWebsitePage::query()
            ->where('page_key', $key)
            ->whereIn('status', [
                PublicWebsitePage::STATUS_APPROVED,
                PublicWebsitePage::STATUS_PUBLISHED,
            ])
            ->exists();
    }

    private function docPresent(string $doc): bool
    {
        return $doc !== '' && is_file($this->repoRoot().'/'.ltrim($doc, '/'));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
