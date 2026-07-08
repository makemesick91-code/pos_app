<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal activity lifecycle.
 *
 * Records manual renewal/dunning communication and review notes, schedules follow-
 * ups, and completes/cancels activities. A WHATSAPP_MANUAL / EMAIL_MANUAL activity
 * is only an internal record of an external manual action — NO real message is
 * ever sent. Secret-looking free-text/metadata is stripped.
 */
class SubscriptionRenewalActivityService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function record(array $attributes, ?User $actor = null): SubscriptionRenewalActivity
    {
        return SubscriptionRenewalActivity::query()->create([
            'activity_reference' => (string) ($attributes['activity_reference'] ?? $this->generateReference()),
            'candidate_id' => $attributes['candidate_id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'tenant_subscription_id' => $attributes['tenant_subscription_id'] ?? null,
            'actor_user_id' => $attributes['actor_user_id'] ?? $actor?->id,
            'activity_type' => $this->normalizeType((string) ($attributes['activity_type'] ?? SubscriptionRenewalActivity::TYPE_NOTE)),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SubscriptionRenewalActivity::STATUS_PLANNED)),
            'summary' => $this->sanitizeString((string) ($attributes['summary'] ?? 'Renewal activity')),
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'scheduled_at' => isset($attributes['scheduled_at']) ? Carbon::parse($attributes['scheduled_at']) : null,
            'completed_at' => isset($attributes['completed_at']) ? Carbon::parse($attributes['completed_at']) : null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    public function complete(SubscriptionRenewalActivity $activity, ?User $actor = null): SubscriptionRenewalActivity
    {
        $activity->status = SubscriptionRenewalActivity::STATUS_DONE;
        $activity->completed_at = Carbon::now();
        $activity->save();

        return $activity->refresh();
    }

    public function cancel(SubscriptionRenewalActivity $activity, ?User $actor = null): SubscriptionRenewalActivity
    {
        $activity->status = SubscriptionRenewalActivity::STATUS_CANCELLED;
        $activity->save();

        return $activity->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SubscriptionRenewalActivity::query()->get();

        $byType = [];
        foreach (SubscriptionRenewalActivity::TYPES as $type) {
            $count = $all->where('activity_type', $type)->count();
            if ($count > 0) {
                $byType[$type] = $count;
            }
        }

        $byStatus = [];
        foreach (SubscriptionRenewalActivity::STATUSES as $status) {
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
            'manual_only' => true,
            'no_real_sending' => true,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, SubscriptionRenewalActivity::TYPES, true)) {
            throw new InvalidArgumentException("Invalid activity type: {$type}");
        }

        return $type;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SubscriptionRenewalActivity::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid activity status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'SRACT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
