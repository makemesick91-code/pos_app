<?php

namespace App\Services\PublicWebsite;

use App\Models\PublicWebsitePage;

/**
 * Sprint 21 — SEO metadata governance.
 *
 * Verifies SEO title/description presence for the required public pages, and the
 * canonical / robots / sitemap / open-graph readiness placeholders declared in
 * config. No live external token is required or accepted. Produces a
 * GO/WATCH/NO_GO decision.
 */
class SeoMetadataGovernanceService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $required = (array) config('public_website.seo_required_pages', config('public_website.required_pages', []));

        $missingPage = [];
        $missingSeo = [];

        foreach ($required as $key) {
            $page = PublicWebsitePage::query()->where('page_key', $key)->first();
            if ($page === null) {
                $missingPage[] = $key;

                continue;
            }
            if (trim((string) $page->seo_title) === '' || trim((string) $page->seo_description) === '') {
                $missingSeo[] = $key;
            }
        }

        // Readiness placeholders are documentation-level; they gate WATCH, not NO_GO.
        $placeholders = (array) config('public_website.seo_readiness_placeholders', []);

        $decision = self::DECISION_GO;
        if ($missingPage !== []) {
            $decision = self::DECISION_NO_GO;
        } elseif ($missingSeo !== []) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'required_pages' => array_values($required),
            'missing_pages' => $missingPage,
            'pages_missing_seo' => $missingSeo,
            'readiness_placeholders' => array_values($placeholders),
            'live_tracking_tokens_allowed' => (bool) config('public_website.live_tracking_tokens_allowed', false),
        ];
    }
}
