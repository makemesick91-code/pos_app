<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteContentSummaryResource;
use App\Services\PublicWebsite\LandingPageContentService;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;

/**
 * Sprint 21 — read-only public website content summary (pages + landing).
 * Platform admin only. No secrets exposed.
 */
class PublicWebsiteContentSummaryController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteReadinessService $readiness,
        private readonly LandingPageContentService $landing,
    ) {}

    public function index(): PublicWebsiteContentSummaryResource
    {
        $pages = $this->readiness->pagesSummary();
        $landing = $this->landing->summary();

        $decision = PublicWebsiteReadinessService::DECISION_GO;
        foreach ([(string) $pages['decision'], (string) $landing['decision']] as $d) {
            if ($d === PublicWebsiteReadinessService::DECISION_NO_GO) {
                $decision = PublicWebsiteReadinessService::DECISION_NO_GO;
                break;
            }
            if ($d === PublicWebsiteReadinessService::DECISION_WATCH) {
                $decision = PublicWebsiteReadinessService::DECISION_WATCH;
            }
        }

        return new PublicWebsiteContentSummaryResource([
            'decision' => $decision,
            'pages' => $pages,
            'landing' => $landing,
        ]);
    }
}
