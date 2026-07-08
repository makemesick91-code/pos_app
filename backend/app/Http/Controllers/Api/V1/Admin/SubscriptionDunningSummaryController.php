<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\SubscriptionDunningSummaryResource;
use App\Services\SubscriptionRenewal\SubscriptionDunningNoticeService;

/**
 * Sprint 24 — read-only subscription dunning summary. Platform admin only.
 * Secret-safe counts by type/status/channel; manual-only, no real sending.
 */
class SubscriptionDunningSummaryController extends Controller
{
    public function __construct(
        private readonly SubscriptionDunningNoticeService $notices,
    ) {}

    public function index(): SubscriptionDunningSummaryResource
    {
        return new SubscriptionDunningSummaryResource($this->notices->summary());
    }
}
