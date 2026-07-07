<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteLeadSummaryResource;
use App\Services\PublicWebsite\LeadInterestGovernanceService;

/**
 * Sprint 21 — read-only interest-only lead summary. Platform admin only. No
 * secrets exposed; leads are interest-only.
 */
class PublicWebsiteLeadSummaryController extends Controller
{
    public function __construct(private readonly LeadInterestGovernanceService $leads) {}

    public function index(): PublicWebsiteLeadSummaryResource
    {
        return new PublicWebsiteLeadSummaryResource($this->leads->summary());
    }
}
