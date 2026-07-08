<?php

namespace App\Services\SalesPipeline;

use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 22 — sales lead activity tracking.
 *
 * Adds notes/calls/demos/follow-ups, schedules follow-ups, completes/cancels
 * activities, and records stage-change / assignment / qualification activities.
 * WHATSAPP_MANUAL and EMAIL_MANUAL are MANUAL NOTES only — no real message is ever
 * sent, no external CRM/webhook is ever called. Free-text is sanitized.
 */
class SalesLeadActivityService
{
    use SanitizesSalesPipelineText;

    /**
     * @param array<string,mixed> $attributes
     */
    public function add(SalesLead $lead, array $attributes, ?User $actor = null): SalesLeadActivity
    {
        $type = $this->normalizeType((string) ($attributes['activity_type'] ?? SalesLeadActivity::TYPE_NOTE));
        $status = $this->normalizeStatus((string) ($attributes['status'] ?? SalesLeadActivity::STATUS_PLANNED));

        return SalesLeadActivity::query()->create([
            'activity_reference' => (string) ($attributes['activity_reference'] ?? $this->generateReference()),
            'sales_lead_id' => $lead->id,
            'actor_user_id' => $attributes['actor_user_id'] ?? $actor?->id,
            'activity_type' => $type,
            'status' => $status,
            'summary' => $this->sanitizeString((string) ($attributes['summary'] ?? $type)),
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'scheduled_at' => isset($attributes['scheduled_at']) ? Carbon::parse((string) $attributes['scheduled_at']) : null,
            'completed_at' => $status === SalesLeadActivity::STATUS_DONE ? Carbon::now() : null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * Record a system-generated activity (stage change / assignment / etc).
     */
    public function record(SalesLead $lead, string $type, string $summary, ?User $actor = null, ?string $notes = null): SalesLeadActivity
    {
        return $this->add($lead, [
            'activity_type' => $type,
            'status' => SalesLeadActivity::STATUS_DONE,
            'summary' => $summary,
            'notes' => $notes,
        ], $actor);
    }

    public function complete(SalesLeadActivity $activity, ?User $actor = null): SalesLeadActivity
    {
        $activity->status = SalesLeadActivity::STATUS_DONE;
        $activity->completed_at = Carbon::now();
        $activity->save();

        return $activity->refresh();
    }

    public function cancel(SalesLeadActivity $activity, ?User $actor = null): SalesLeadActivity
    {
        $activity->status = SalesLeadActivity::STATUS_CANCELLED;
        $activity->save();

        return $activity->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SalesLeadActivity::query()->get();
        $now = Carbon::now();

        $overdue = $all
            ->filter(fn (SalesLeadActivity $a) => $a->status === SalesLeadActivity::STATUS_PLANNED
                && $a->scheduled_at !== null
                && $a->scheduled_at->lt($now))
            ->count();

        $byType = [];
        foreach (SalesLeadActivity::TYPES as $type) {
            $count = $all->where('activity_type', $type)->count();
            if ($count > 0) {
                $byType[$type] = $count;
            }
        }

        return [
            'decision' => 'GO',
            'total' => $all->count(),
            'planned' => $all->where('status', SalesLeadActivity::STATUS_PLANNED)->count(),
            'done' => $all->where('status', SalesLeadActivity::STATUS_DONE)->count(),
            'cancelled' => $all->where('status', SalesLeadActivity::STATUS_CANCELLED)->count(),
            'skipped' => $all->where('status', SalesLeadActivity::STATUS_SKIPPED)->count(),
            'overdue_placeholder' => $overdue,
            'by_type' => $byType,
            'manual_follow_up_only' => true,
        ];
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));
        if (! in_array($type, SalesLeadActivity::TYPES, true)) {
            throw new InvalidArgumentException("Invalid activity type: {$type}");
        }

        return $type;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SalesLeadActivity::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid activity status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'ACT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
