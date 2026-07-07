<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteReadinessResource;
use App\Services\PublicWebsite\PublicWebsiteReadinessService;

/**
 * Sprint 21 — read-only public website readiness. Platform admin only. Aggregates
 * pages/landing/lead/SEO/privacy/risk/signoff readiness. No secrets exposed.
 */
class PublicWebsiteReadinessController extends Controller
{
    public function __construct(private readonly PublicWebsiteReadinessService $readiness) {}

    public function index(): PublicWebsiteReadinessResource
    {
        return new PublicWebsiteReadinessResource($this->readiness->evaluate());
    }
}
