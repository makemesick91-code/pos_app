<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SubscriptionDunningNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionDunningNotice
 *
 * Sprint 24 — presents a MANUAL subscription dunning notice. No secrets are
 * exposed and no real message is ever sent.
 */
class SubscriptionDunningNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notice_reference' => $this->notice_reference,
            'candidate_id' => $this->candidate_id,
            'tenant_id' => $this->tenant_id,
            'tenant_subscription_id' => $this->tenant_subscription_id,
            'billing_invoice_id' => $this->billing_invoice_id,
            'notice_type' => $this->notice_type,
            'status' => $this->status,
            'channel' => $this->channel,
            'scheduled_for' => $this->scheduled_for,
            'prepared_at' => $this->prepared_at,
            'marked_sent_manually_at' => $this->marked_sent_manually_at,
            'completed_at' => $this->completed_at,
            'actor_user_id' => $this->actor_user_id,
            'summary' => $this->summary,
            'message_template_key' => $this->message_template_key,
            'manual_message_preview' => $this->manual_message_preview,
            'notes' => $this->notes,
            'manual_only' => true,
            'no_real_sending' => true,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
