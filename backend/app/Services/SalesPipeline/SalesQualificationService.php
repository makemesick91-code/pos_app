<?php

namespace App\Services\SalesPipeline;

use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 22 — sales lead qualification readiness.
 *
 * Calculates a qualification readiness score from contact/business/estimate/
 * package/activity completeness, and records manual qualify / lost /
 * ready-for-onboarding transitions. ready_for_onboarding means a MANUAL onboarding
 * review is due — it NEVER creates a tenant/user/subscription/device. The
 * qualification score is advisory and never bypasses manual approval.
 */
class SalesQualificationService
{
    /**
     * Compute a 0–100 qualification readiness score. Advisory only.
     */
    public function score(SalesLead $lead): int
    {
        $score = 0;

        if (filled($lead->business_name)) {
            $score += 15;
        }
        if (filled($lead->contact_name)) {
            $score += 10;
        }
        if (filled($lead->contact_email) || filled($lead->contact_phone)) {
            $score += 20;
        }
        if (filled($lead->business_type)) {
            $score += 10;
        }
        if (($lead->estimated_store_count ?? 0) > 0) {
            $score += 10;
        }
        if (($lead->estimated_device_count ?? 0) > 0) {
            $score += 10;
        }
        if (filled($lead->interest_package_code)) {
            $score += 15;
        }
        if (SalesLeadActivity::query()->where('sales_lead_id', $lead->id)->where('status', SalesLeadActivity::STATUS_DONE)->exists()) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * @return array<string,mixed>
     */
    public function readiness(SalesLead $lead): array
    {
        $score = $this->score($lead);

        return [
            'lead_id' => $lead->id,
            'qualification_score' => $score,
            'ready_for_qualification' => $score >= 60,
            'ready_for_onboarding' => $lead->ready_for_onboarding_at !== null,
            'requires_manual_review' => true,
        ];
    }

    public function markQualified(SalesLead $lead, ?User $actor = null): SalesLead
    {
        $lead->qualification_score = $this->score($lead);
        $lead->status = SalesLead::STATUS_QUALIFIED;
        $lead->qualified_at = Carbon::now();
        $lead->save();

        return $lead->refresh();
    }

    public function markLost(SalesLead $lead, ?string $reason = null, ?User $actor = null): SalesLead
    {
        $lead->status = SalesLead::STATUS_LOST;
        $lead->lost_at = Carbon::now();
        $lead->lost_reason = $reason;
        $lead->save();

        return $lead->refresh();
    }

    /**
     * Mark the lead ready for a MANUAL onboarding review. This never creates a
     * tenant, user, subscription, or device.
     */
    public function markReadyForOnboarding(SalesLead $lead, ?User $actor = null): SalesLead
    {
        $lead->qualification_score = $this->score($lead);
        $lead->status = SalesLead::STATUS_WON_READY_FOR_ONBOARDING;
        $lead->ready_for_onboarding_at = Carbon::now();
        if ($lead->qualified_at === null) {
            $lead->qualified_at = Carbon::now();
        }
        $lead->save();

        return $lead->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $qualified = SalesLead::query()->whereNotNull('qualified_at')->count();
        $lost = SalesLead::query()->whereNotNull('lost_at')->count();
        $ready = SalesLead::query()->whereNotNull('ready_for_onboarding_at')->count();

        return [
            'decision' => 'GO',
            'qualified' => $qualified,
            'lost' => $lost,
            'ready_for_onboarding' => $ready,
            'auto_provisioning' => false,
        ];
    }
}
