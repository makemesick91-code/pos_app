<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\PublicWebsiteGoNoGoResource;
use App\Services\PublicWebsite\PublicWebsiteGoNoGoService;

/**
 * Sprint 21 — read-only public website GO/WATCH/NO-GO. Platform admin only.
 * Aggregates all prior gates + public website readiness. No secrets exposed;
 * nothing is deployed or billed.
 */
class PublicWebsiteGoNoGoController extends Controller
{
    public function __construct(private readonly PublicWebsiteGoNoGoService $goNoGo) {}

    public function index(): PublicWebsiteGoNoGoResource
    {
        return new PublicWebsiteGoNoGoResource($this->goNoGo->evaluate());
    }
}
