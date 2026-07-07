<?php

namespace App\Services\PublicWebsite;

use App\Models\LeadInterestSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 21 — lead interest governance.
 *
 * Validates and stores INTEREST-ONLY public lead submissions. A lead NEVER
 * creates a tenant, user, subscription, or device and NEVER triggers real
 * email/WhatsApp/CRM. Consent is required (a consent timestamp). Secret-looking
 * input is sanitized. Summarizes leads by status/source/package/business type and
 * asserts interest-only behavior for the readiness gate.
 */
class LeadInterestGovernanceService
{
    use SanitizesPublicWebsiteText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * Store an interest-only lead. Requires consent. Never provisions anything.
     *
     * @param array<string,mixed> $attributes
     */
    public function submit(array $attributes, ?User $actor = null): LeadInterestSubmission
    {
        $consent = $attributes['consent_accepted_at'] ?? (($attributes['consent'] ?? false) ? Carbon::now() : null);
        if ($consent === null) {
            throw new InvalidArgumentException('Lead submission requires consent.');
        }

        return LeadInterestSubmission::query()->create([
            'lead_reference' => (string) ($attributes['lead_reference'] ?? $this->generateReference()),
            'status' => LeadInterestSubmission::STATUS_NEW,
            'business_name' => $this->sanitizeNullableString($attributes['business_name'] ?? null),
            'contact_name' => $this->sanitizeNullableString($attributes['contact_name'] ?? null),
            'contact_email' => $this->sanitizeNullableString($attributes['contact_email'] ?? null),
            'contact_phone' => $this->sanitizeNullableString($attributes['contact_phone'] ?? null),
            'business_type' => $this->sanitizeNullableString($attributes['business_type'] ?? null),
            'estimated_store_count' => isset($attributes['estimated_store_count']) ? (int) $attributes['estimated_store_count'] : null,
            'estimated_device_count' => isset($attributes['estimated_device_count']) ? (int) $attributes['estimated_device_count'] : null,
            'interest_package_code' => $this->sanitizeNullableString($attributes['interest_package_code'] ?? null),
            'message' => $this->sanitizeNullableString($attributes['message'] ?? null),
            'source' => $this->sanitizeNullableString($attributes['source'] ?? 'public-website'),
            'consent_accepted_at' => $consent instanceof Carbon ? $consent : Carbon::parse((string) $consent),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    public function changeStatus(LeadInterestSubmission $lead, string $status, ?User $actor = null): LeadInterestSubmission
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, LeadInterestSubmission::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid lead status: {$status}");
        }

        $lead->status = $status;
        if (in_array($status, [
            LeadInterestSubmission::STATUS_CONTACTED,
            LeadInterestSubmission::STATUS_QUALIFIED,
            LeadInterestSubmission::STATUS_DISQUALIFIED,
        ], true) && $lead->processed_at === null) {
            $lead->processed_at = Carbon::now();
        }
        $lead->save();

        return $lead->refresh();
    }

    /**
     * Summarize leads by status/source/package/business type. Leads are always
     * interest-only, so the decision is GO unless there are leads missing consent
     * (a data-integrity WATCH signal — should never happen via the public form).
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = LeadInterestSubmission::query()->get();

        $byStatus = [];
        foreach (LeadInterestSubmission::STATUSES as $status) {
            $byStatus[$status] = $all->where('status', $status)->count();
        }

        $missingConsent = $all->whereNull('consent_accepted_at')->count();

        $decision = $missingConsent > 0 ? self::DECISION_WATCH : self::DECISION_GO;

        return [
            'decision' => $decision,
            'interest_only' => true,
            'counts' => [
                'total' => $all->count(),
                'by_status' => $byStatus,
                'new' => $byStatus[LeadInterestSubmission::STATUS_NEW],
                'spam' => $byStatus[LeadInterestSubmission::STATUS_SPAM],
                'missing_consent' => $missingConsent,
            ],
            'by_source' => $all->groupBy('source')->map->count()->all(),
            'by_package' => $all->groupBy('interest_package_code')->map->count()->all(),
            'by_business_type' => $all->groupBy('business_type')->map->count()->all(),
        ];
    }

    private function generateReference(): string
    {
        return 'LEAD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
