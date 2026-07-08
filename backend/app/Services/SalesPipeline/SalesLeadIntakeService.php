<?php

namespace App\Services\SalesPipeline;

use App\Models\LeadInterestSubmission;
use App\Models\SalesLead;
use App\Models\SalesPipelineStage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Sprint 22 — sales lead intake.
 *
 * Creates a SalesLead manually or imports one from a Sprint 21 lead interest
 * submission. Deduplicates by lead_interest_submission_id (an already-imported
 * submission replays its existing lead) and sanitizes secret-looking free-text.
 *
 * A sales lead is intake/pipeline data ONLY: this service NEVER creates a tenant,
 * user, subscription, or device and NEVER triggers real billing/CRM/messaging.
 */
class SalesLeadIntakeService
{
    use SanitizesSalesPipelineText;

    /**
     * Create a sales lead manually.
     *
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SalesLead
    {
        return SalesLead::query()->create($this->buildAttributes($attributes, 'manual'));
    }

    /**
     * Import (or replay) a sales lead from a public-website lead interest
     * submission. Idempotent by lead_interest_submission_id.
     */
    public function importFromInterest(LeadInterestSubmission $submission, ?User $actor = null): SalesLead
    {
        $existing = SalesLead::query()
            ->where('lead_interest_submission_id', $submission->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $attributes = [
            'lead_interest_submission_id' => $submission->id,
            'business_name' => $submission->business_name,
            'contact_name' => $submission->contact_name,
            'contact_email' => $submission->contact_email,
            'contact_phone' => $submission->contact_phone,
            'business_type' => $submission->business_type,
            'estimated_store_count' => $submission->estimated_store_count,
            'estimated_device_count' => $submission->estimated_device_count,
            'interest_package_code' => $submission->interest_package_code,
            'notes' => $submission->message,
        ];

        return SalesLead::query()->create(
            $this->buildAttributes($attributes, (string) ($submission->source ?: 'public-website'), $submission->id),
        );
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SalesLead $lead, array $attributes, ?User $actor = null): SalesLead
    {
        $map = [
            'business_name' => fn ($v) => $this->sanitizeNullableString($v),
            'contact_name' => fn ($v) => $this->sanitizeNullableString($v),
            'contact_email' => fn ($v) => $this->sanitizeNullableString($v),
            'contact_phone' => fn ($v) => $this->sanitizeNullableString($v),
            'business_type' => fn ($v) => $this->sanitizeNullableString($v),
            'estimated_store_count' => fn ($v) => $v === null ? null : (int) $v,
            'estimated_device_count' => fn ($v) => $v === null ? null : (int) $v,
            'interest_package_code' => fn ($v) => $this->sanitizeNullableString($v),
            'priority' => fn ($v) => $this->normalizePriority((string) $v),
            'notes' => fn ($v) => $this->sanitizeNullableString($v),
            'evidence_reference' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $lead->{$key} = $caster($attributes[$key]);
            }
        }

        $lead->save();

        return $lead->refresh();
    }

    /**
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    private function buildAttributes(array $attributes, string $defaultSource, ?int $submissionId = null): array
    {
        return [
            'lead_reference' => (string) ($attributes['lead_reference'] ?? $this->generateReference()),
            'lead_interest_submission_id' => $submissionId ?? ($attributes['lead_interest_submission_id'] ?? null),
            'pipeline_stage_id' => $this->defaultStageId(),
            'status' => SalesLead::STATUS_NEW,
            'source' => $this->sanitizeString((string) ($attributes['source'] ?? $defaultSource)),
            'business_name' => $this->sanitizeNullableString($attributes['business_name'] ?? null),
            'contact_name' => $this->sanitizeNullableString($attributes['contact_name'] ?? null),
            'contact_email' => $this->sanitizeNullableString($attributes['contact_email'] ?? null),
            'contact_phone' => $this->sanitizeNullableString($attributes['contact_phone'] ?? null),
            'business_type' => $this->sanitizeNullableString($attributes['business_type'] ?? null),
            'estimated_store_count' => isset($attributes['estimated_store_count']) ? (int) $attributes['estimated_store_count'] : null,
            'estimated_device_count' => isset($attributes['estimated_device_count']) ? (int) $attributes['estimated_device_count'] : null,
            'interest_package_code' => $this->sanitizeNullableString($attributes['interest_package_code'] ?? null),
            'priority' => $this->normalizePriority((string) ($attributes['priority'] ?? SalesLead::PRIORITY_NORMAL)),
            'notes' => $this->sanitizeNullableString($attributes['notes'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ];
    }

    private function defaultStageId(): ?int
    {
        $stage = SalesPipelineStage::query()->where('is_default', true)->first()
            ?? SalesPipelineStage::query()->where('stage_code', SalesPipelineStage::CODE_NEW)->first();

        return $stage?->id;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtoupper(trim($priority));

        return in_array($priority, SalesLead::PRIORITIES, true) ? $priority : SalesLead::PRIORITY_NORMAL;
    }

    private function generateReference(): string
    {
        return 'LEAD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    /**
     * Lead summary by status/stage/source/priority + ready-for-onboarding count.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SalesLead::query()->get();

        $byStatus = [];
        foreach (SalesLead::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        $byPriority = [];
        foreach (SalesLead::PRIORITIES as $priority) {
            $count = $all->where('priority', $priority)->count();
            if ($count > 0) {
                $byPriority[$priority] = $count;
            }
        }

        $bySource = $all->groupBy('source')->map->count()->all();

        $byStage = [];
        foreach ($all->groupBy('pipeline_stage_id') as $stageId => $group) {
            $code = $stageId === null || $stageId === ''
                ? 'UNSTAGED'
                : (string) (SalesPipelineStage::query()->find($stageId)?->stage_code ?? 'UNSTAGED');
            $byStage[$code] = ($byStage[$code] ?? 0) + $group->count();
        }

        return [
            'decision' => 'GO',
            'total_leads' => $all->count(),
            'by_status' => $byStatus,
            'by_stage' => $byStage,
            'by_source' => $bySource,
            'by_priority' => $byPriority,
            'ready_for_onboarding' => $all->whereNotNull('ready_for_onboarding_at')->count(),
        ];
    }
}
