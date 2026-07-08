<?php

namespace App\Services\SalesPipeline;

use App\Models\SalesLead;
use App\Models\SalesPipelineStage;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 22 — sales pipeline stage lifecycle.
 *
 * Owns create/list/update of pipeline stages, ensures the canonical default stage
 * set exists, and transitions a lead onto a stage. Terminal stages
 * (WON_READY_FOR_ONBOARDING / LOST / ARCHIVED) mean manual review only and cannot
 * silently reopen without an activity note. A stage NEVER creates a tenant/user/
 * subscription/device and never bills. No secrets are stored.
 */
class SalesPipelineStageService
{
    use SanitizesSalesPipelineText;

    /**
     * Idempotently create the canonical default stage set from config.
     *
     * @return array<int,SalesPipelineStage>
     */
    public function ensureDefaults(): array
    {
        $definitions = (array) config('sales_pipeline.default_stage_definitions', []);

        $stages = [];
        foreach ($definitions as $definition) {
            $stages[] = SalesPipelineStage::query()->updateOrCreate(
                ['stage_code' => $definition['stage_code']],
                [
                    'name' => $definition['name'],
                    'sort_order' => (int) ($definition['sort_order'] ?? 0),
                    'status' => SalesPipelineStage::STATUS_ACTIVE,
                    'is_default' => (bool) ($definition['is_default'] ?? false),
                    'is_terminal' => (bool) ($definition['is_terminal'] ?? false),
                ],
            );
        }

        return $stages;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SalesPipelineStage
    {
        $code = strtoupper(trim((string) ($attributes['stage_code'] ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException('Stage code is required.');
        }

        return SalesPipelineStage::query()->create([
            'stage_code' => $code,
            'name' => $this->sanitizeString((string) ($attributes['name'] ?? $code)),
            'description' => $this->sanitizeNullableString($attributes['description'] ?? null),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SalesPipelineStage::STATUS_ACTIVE)),
            'is_default' => (bool) ($attributes['is_default'] ?? false),
            'is_terminal' => (bool) ($attributes['is_terminal'] ?? false),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SalesPipelineStage $stage, array $attributes, ?User $actor = null): SalesPipelineStage
    {
        $map = [
            'name' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'sort_order' => fn ($v) => (int) $v,
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'is_default' => fn ($v) => (bool) $v,
            'is_terminal' => fn ($v) => (bool) $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $stage->{$key} = $caster($attributes[$key]);
            }
        }

        $stage->save();

        return $stage->refresh();
    }

    /**
     * Transition a lead onto a stage. Terminal stages set the corresponding lead
     * status; reopening from a terminal lead status requires an explicit
     * activity note recorded by the caller (SalesLeadActivityService).
     */
    public function transitionLead(SalesLead $lead, string $stageCode, ?User $actor = null): SalesLead
    {
        $stageCode = strtoupper(trim($stageCode));
        $stage = SalesPipelineStage::query()->where('stage_code', $stageCode)->first();
        if ($stage === null) {
            throw new InvalidArgumentException("Unknown pipeline stage: {$stageCode}");
        }

        $lead->pipeline_stage_id = $stage->id;

        // Keep the lead's flat status column aligned with the stage where a
        // matching status exists (conservative, no auto-provisioning).
        if (in_array($stageCode, SalesLead::STATUSES, true)) {
            $lead->status = $stageCode;
        }

        $lead->save();

        return $lead->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $canonical = (array) config('sales_pipeline.canonical_stages', []);
        $existing = SalesPipelineStage::query()->pluck('stage_code')->all();
        $missing = array_values(array_diff($canonical, $existing));

        $byStage = [];
        foreach (SalesPipelineStage::query()->orderBy('sort_order')->get() as $stage) {
            $byStage[$stage->stage_code] = SalesLead::query()->where('pipeline_stage_id', $stage->id)->count();
        }

        return [
            'decision' => $missing === [] ? 'GO' : 'NO_GO',
            'canonical' => array_values($canonical),
            'existing' => $existing,
            'missing' => $missing,
            'lead_counts_by_stage' => $byStage,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SalesPipelineStage::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid stage status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'STAGE-'.strtoupper(Str::random(6));
    }
}
