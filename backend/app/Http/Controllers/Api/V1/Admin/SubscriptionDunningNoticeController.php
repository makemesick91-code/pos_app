<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSubscriptionDunningNoticeRequest;
use App\Http\Requests\Api\V1\Admin\TransitionSubscriptionDunningNoticeRequest;
use App\Http\Resources\Api\V1\Admin\SubscriptionDunningNoticeResource;
use App\Models\AdminAuditLog;
use App\Models\SubscriptionDunningNotice;
use App\Models\SubscriptionRenewalCandidate;
use App\Services\Admin\AdminAuditLogger;
use App\Services\SubscriptionRenewal\SubscriptionDunningNoticeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 24 — platform-admin MANUAL subscription dunning notices. Platform admin
 * only. Notices are a manual reminder queue; MARKED_SENT_MANUALLY records an
 * external manual action and NO real message is ever sent. Every mutation is
 * audit-logged.
 */
class SubscriptionDunningNoticeController extends Controller
{
    public function __construct(
        private readonly SubscriptionDunningNoticeService $notices,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(Request $request, SubscriptionRenewalCandidate $candidate): AnonymousResourceCollection
    {
        return SubscriptionDunningNoticeResource::collection(
            $candidate->dunningNotices()->latest('id')->paginate((int) $request->integer('per_page', 20)),
        );
    }

    public function store(StoreSubscriptionDunningNoticeRequest $request, SubscriptionRenewalCandidate $candidate): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->prepare($candidate, $request->validated(), $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_PREPARED, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    public function prepare(TransitionSubscriptionDunningNoticeRequest $request, SubscriptionDunningNotice $notice): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->markPrepared($notice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_MARKED_PREPARED, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    public function markSentManually(TransitionSubscriptionDunningNoticeRequest $request, SubscriptionDunningNotice $notice): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->markSentManually($notice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_MARKED_SENT_MANUALLY, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    public function complete(TransitionSubscriptionDunningNoticeRequest $request, SubscriptionDunningNotice $notice): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->complete($notice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_COMPLETED, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    public function cancel(TransitionSubscriptionDunningNoticeRequest $request, SubscriptionDunningNotice $notice): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->cancel($notice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_CANCELLED, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    public function skip(TransitionSubscriptionDunningNoticeRequest $request, SubscriptionDunningNotice $notice): SubscriptionDunningNoticeResource
    {
        $notice = $this->notices->skip($notice, $request->user());
        $this->log($request, AdminAuditLog::ACTION_DUNNING_NOTICE_SKIPPED, $notice);

        return new SubscriptionDunningNoticeResource($notice);
    }

    private function log(Request $request, string $action, SubscriptionDunningNotice $notice): void
    {
        $this->audit->log(
            actor: $request->user(),
            action: $action,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION_DUNNING_NOTICE,
            targetId: $notice->id,
            after: ['status' => $notice->status, 'channel' => $notice->channel, 'notice_type' => $notice->notice_type],
            request: $request,
        );
    }
}
