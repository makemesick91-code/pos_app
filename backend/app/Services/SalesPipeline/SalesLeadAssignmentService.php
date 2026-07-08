<?php

namespace App\Services\SalesPipeline;

use App\Models\SalesLead;
use App\Models\SalesLeadAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sprint 22 — sales lead assignment governance.
 *
 * Assigns/reassigns/unassigns leads while preserving assignment history. Internal
 * sales ownership only — never provisions anything and never bills. Free-text is
 * sanitized.
 */
class SalesLeadAssignmentService
{
    use SanitizesSalesPipelineText;

    /**
     * Assign a lead to a user, marking any prior active assignment as reassigned.
     *
     * @param array<string,mixed> $attributes
     */
    public function assign(SalesLead $lead, array $attributes, ?User $actor = null): SalesLeadAssignment
    {
        $assignedTo = $attributes['assigned_to_user_id'] ?? null;

        $this->closeActiveAssignments($lead, SalesLeadAssignment::STATUS_REASSIGNED);

        $assignment = SalesLeadAssignment::query()->create([
            'assignment_reference' => (string) ($attributes['assignment_reference'] ?? $this->generateReference()),
            'sales_lead_id' => $lead->id,
            'assigned_to_user_id' => $assignedTo,
            'assigned_by_user_id' => $attributes['assigned_by_user_id'] ?? $actor?->id,
            'status' => SalesLeadAssignment::STATUS_ACTIVE,
            'assigned_at' => Carbon::now(),
            'reason' => $this->sanitizeNullableString($attributes['reason'] ?? null),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);

        $lead->assigned_to_user_id = $assignedTo;
        $lead->save();

        return $assignment->refresh();
    }

    public function unassign(SalesLead $lead, ?User $actor = null, ?string $reason = null): void
    {
        $this->closeActiveAssignments($lead, SalesLeadAssignment::STATUS_UNASSIGNED, $reason);

        $lead->assigned_to_user_id = null;
        $lead->save();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $active = SalesLeadAssignment::query()->active()->get();

        $byUser = $active->groupBy('assigned_to_user_id')->map->count()->all();

        return [
            'decision' => 'GO',
            'active_assignments' => $active->count(),
            'total_assignments' => SalesLeadAssignment::query()->count(),
            'active_by_user' => $byUser,
        ];
    }

    private function closeActiveAssignments(SalesLead $lead, string $status, ?string $reason = null): void
    {
        SalesLeadAssignment::query()
            ->where('sales_lead_id', $lead->id)
            ->where('status', SalesLeadAssignment::STATUS_ACTIVE)
            ->get()
            ->each(function (SalesLeadAssignment $assignment) use ($status, $reason) {
                $assignment->status = $status;
                $assignment->unassigned_at = Carbon::now();
                if ($reason !== null) {
                    $assignment->reason = $this->sanitizeString($reason);
                }
                $assignment->save();
            });
    }

    private function generateReference(): string
    {
        return 'ASSIGN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
