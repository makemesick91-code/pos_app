<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingCollectionActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing collection activity lifecycle.
 *
 * Records manual collection notes/calls/follow-ups and schedules them. Activities
 * of type WHATSAPP_MANUAL / EMAIL_MANUAL are NOTES ONLY — this service NEVER sends
 * a real WhatsApp/email/Slack message. Complete/cancel transitions and a summary by
 * type/status are provided. Secret-looking free-text/metadata is stripped.
 */
class BillingCollectionActivityService
{
    use SanitizesBillingCollectionText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data, ?User $actor = null): SaasBillingCollectionActivity
    {
        return SaasBillingCollectionActivity::query()->create([
            'activity_reference' => (string) ($data['activity_reference'] ?? $this->generateReference()),
            'billing_account_id' => $data['billing_account_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'actor_user_id' => $actor?->id ?? ($data['actor_user_id'] ?? null),
            'activity_type' => $this->normalizeType((string) ($data['activity_type'] ?? SaasBillingCollectionActivity::TYPE_NOTE)),
            'status' => $this->normalizeStatus((string) ($data['status'] ?? SaasBillingCollectionActivity::STATUS_PLANNED)),
            'summary' => $this->sanitizeString((string) ($data['summary'] ?? 'Collection activity')),
            'notes' => $this->sanitizeNullableString($data['notes'] ?? null),
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse((string) $data['scheduled_at']) : null,
            'metadata' => $this->sanitizeArray($data['metadata'] ?? null),
        ]);
    }

    public function complete(SaasBillingCollectionActivity $activity, ?User $actor = null): SaasBillingCollectionActivity
    {
        if (in_array($activity->status, [SaasBillingCollectionActivity::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException("Cannot complete a {$activity->status} activity.");
        }

        $activity->status = SaasBillingCollectionActivity::STATUS_DONE;
        $activity->completed_at = Carbon::now();
        $activity->save();

        return $activity->refresh();
    }

    public function cancel(SaasBillingCollectionActivity $activity, ?User $actor = null): SaasBillingCollectionActivity
    {
        if ($activity->status === SaasBillingCollectionActivity::STATUS_DONE) {
            throw new InvalidArgumentException('Cannot cancel a completed activity.');
        }

        $activity->status = SaasBillingCollectionActivity::STATUS_CANCELLED;
        $activity->save();

        return $activity->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasBillingCollectionActivity::query()->get();

        $byType = [];
        foreach (SaasBillingCollectionActivity::ACTIVITY_TYPES as $type) {
            $count = $all->where('activity_type', $type)->count();
            if ($count > 0) {
                $byType[$type] = $count;
            }
        }

        $byStatus = [];
        foreach (SaasBillingCollectionActivity::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_activities' => $all->count(),
            'by_type' => $byType,
            'by_status' => $byStatus,
            'manual_follow_up_only' => true,
            'no_real_sending' => true,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));

        return in_array($type, SaasBillingCollectionActivity::ACTIVITY_TYPES, true)
            ? $type
            : SaasBillingCollectionActivity::TYPE_NOTE;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));

        return in_array($status, SaasBillingCollectionActivity::STATUSES, true)
            ? $status
            : SaasBillingCollectionActivity::STATUS_PLANNED;
    }

    private function generateReference(): string
    {
        return 'BCACT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
