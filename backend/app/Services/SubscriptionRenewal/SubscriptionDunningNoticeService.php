<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionDunningNotice;
use App\Models\SubscriptionRenewalCandidate;
use App\Models\SubscriptionRenewalPolicy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sprint 24 — subscription dunning notice lifecycle.
 *
 * Prepares and tracks a MANUAL dunning reminder queue. A notice is scheduled,
 * prepared, marked-sent-manually (an admin recording an external action),
 * completed, cancelled or skipped. The per-candidate count is capped by the
 * policy's max_manual_dunning_notices. NO real email/WhatsApp/SMS is ever sent
 * and no secrets are stored — the manual_message_preview is sanitized.
 */
class SubscriptionDunningNoticeService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function prepare(SubscriptionRenewalCandidate $candidate, array $attributes, ?User $actor = null): SubscriptionDunningNotice
    {
        $this->enforceMaxNotices($candidate);

        return SubscriptionDunningNotice::query()->create([
            'notice_reference' => (string) ($attributes['notice_reference'] ?? $this->generateReference()),
            'candidate_id' => $candidate->id,
            'tenant_id' => $attributes['tenant_id'] ?? $candidate->tenant_id,
            'tenant_subscription_id' => $attributes['tenant_subscription_id'] ?? $candidate->tenant_subscription_id,
            'billing_invoice_id' => $attributes['billing_invoice_id'] ?? $candidate->billing_invoice_id,
            'notice_type' => $this->normalizeType((string) ($attributes['notice_type'] ?? SubscriptionDunningNotice::TYPE_RENEWAL_REMINDER)),
            'status' => SubscriptionDunningNotice::STATUS_PLANNED,
            'channel' => $this->normalizeChannel((string) ($attributes['channel'] ?? SubscriptionDunningNotice::CHANNEL_IN_APP_ADMIN_NOTE)),
            'scheduled_for' => isset($attributes['scheduled_for']) ? Carbon::parse($attributes['scheduled_for']) : null,
            'actor_user_id' => $attributes['actor_user_id'] ?? $actor?->id,
            'summary' => $this->sanitizeString((string) ($attributes['summary'] ?? 'Manual dunning reminder')),
            'message_template_key' => $this->sanitizeNullableString($attributes['message_template_key'] ?? null),
            'manual_message_preview' => $this->sanitizeNullableString($attributes['manual_message_preview'] ?? null),
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    public function markPrepared(SubscriptionDunningNotice $notice, ?User $actor = null): SubscriptionDunningNotice
    {
        $notice->status = SubscriptionDunningNotice::STATUS_PREPARED;
        $notice->prepared_at = Carbon::now();
        $notice->save();

        return $notice->refresh();
    }

    /**
     * Record that an admin sent the notice via an external MANUAL channel. This
     * never dispatches a real message; it only stamps the manual action.
     */
    public function markSentManually(SubscriptionDunningNotice $notice, ?User $actor = null): SubscriptionDunningNotice
    {
        $notice->status = SubscriptionDunningNotice::STATUS_MARKED_SENT_MANUALLY;
        $notice->marked_sent_manually_at = Carbon::now();
        $notice->actor_user_id = $actor?->id ?? $notice->actor_user_id;
        $notice->save();

        return $notice->refresh();
    }

    public function complete(SubscriptionDunningNotice $notice, ?User $actor = null): SubscriptionDunningNotice
    {
        $notice->status = SubscriptionDunningNotice::STATUS_COMPLETED;
        $notice->completed_at = Carbon::now();
        $notice->save();

        return $notice->refresh();
    }

    public function cancel(SubscriptionDunningNotice $notice, ?User $actor = null): SubscriptionDunningNotice
    {
        $notice->status = SubscriptionDunningNotice::STATUS_CANCELLED;
        $notice->save();

        return $notice->refresh();
    }

    public function skip(SubscriptionDunningNotice $notice, ?User $actor = null): SubscriptionDunningNotice
    {
        $notice->status = SubscriptionDunningNotice::STATUS_SKIPPED;
        $notice->save();

        return $notice->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SubscriptionDunningNotice::query()->get();

        $byType = [];
        foreach (SubscriptionDunningNotice::TYPES as $type) {
            $count = $all->where('notice_type', $type)->count();
            if ($count > 0) {
                $byType[$type] = $count;
            }
        }

        $byStatus = [];
        foreach (SubscriptionDunningNotice::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        $byChannel = [];
        foreach (SubscriptionDunningNotice::CHANNELS as $channel) {
            $count = $all->where('channel', $channel)->count();
            if ($count > 0) {
                $byChannel[$channel] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_notices' => $all->count(),
            'notices_by_type' => $byType,
            'notices_by_status' => $byStatus,
            'notices_by_channel' => $byChannel,
            'manual_only' => true,
            'no_real_sending' => true,
        ];
    }

    private function enforceMaxNotices(SubscriptionRenewalCandidate $candidate): void
    {
        $max = $this->maxNoticesFor($candidate);
        $active = SubscriptionDunningNotice::query()
            ->where('candidate_id', $candidate->id)
            ->whereNotIn('status', [
                SubscriptionDunningNotice::STATUS_CANCELLED,
                SubscriptionDunningNotice::STATUS_SKIPPED,
            ])
            ->count();

        if ($active >= $max) {
            throw new RuntimeException("Maximum manual dunning notices ({$max}) reached for this candidate.");
        }
    }

    private function maxNoticesFor(SubscriptionRenewalCandidate $candidate): int
    {
        if ($candidate->policy_id !== null) {
            $policy = SubscriptionRenewalPolicy::query()->find($candidate->policy_id);
            if ($policy !== null) {
                return max(1, (int) $policy->max_manual_dunning_notices);
            }
        }

        return max(1, (int) config('subscription_renewal.default_policy.max_manual_dunning_notices', 3));
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, SubscriptionDunningNotice::TYPES, true)) {
            throw new InvalidArgumentException("Invalid dunning notice type: {$type}");
        }

        return $type;
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtoupper(trim($channel));
        if (! in_array($channel, SubscriptionDunningNotice::CHANNELS, true)) {
            throw new InvalidArgumentException("Invalid dunning channel: {$channel}");
        }

        return $channel;
    }

    private function generateReference(): string
    {
        return 'SRDUN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
