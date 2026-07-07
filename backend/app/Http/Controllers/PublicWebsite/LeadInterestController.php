<?php

namespace App\Http\Controllers\PublicWebsite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicWebsite\StoreLeadInterestRequest;
use App\Services\PublicWebsite\LeadInterestGovernanceService;
use Illuminate\Http\RedirectResponse;

/**
 * Sprint 21 — public interest-only lead capture (POST /interest).
 *
 * Stores an interest-only lead submission and redirects to the thank-you page.
 * This endpoint NEVER creates a tenant/user/subscription/device, NEVER sends a
 * real email/WhatsApp, and NEVER opens self-service signup. It is rate-limited via
 * the route middleware and requires explicit consent.
 */
class LeadInterestController extends Controller
{
    public function __construct(private readonly LeadInterestGovernanceService $leads) {}

    public function store(StoreLeadInterestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['consent'] = true;
        $data['source'] = 'public-website';

        $this->leads->submit($data, null);

        return redirect('/thank-you');
    }
}
