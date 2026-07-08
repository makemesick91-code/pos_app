<?php

namespace App\Services\SupportOperations;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantDeviceActivation;
use App\Models\TenantProvisioningRun;
use Illuminate\Support\Carbon;

/**
 * Sprint 35 — the canonical tenant health computation (SUP-R002/R014).
 *
 * Aggregates safe status from tenant lifecycle, billing (Sprint 30), payment
 * (Sprint 31), entitlement (Sprint 32), onboarding (Sprint 33), device/sync
 * (Sprint 34) and support incidents into ONE deterministic, explainable health
 * status with safe reason codes. Manual suspension always wins (critical). No
 * PII/secrets. This is the only place tenant health is computed.
 */
class SupportTenantHealthService
{
    public function __construct(
        private readonly SupportBillingViewerService $billing,
        private readonly SupportPaymentViewerService $payment,
        private readonly SupportEntitlementViewerService $entitlement,
        private readonly SupportOnboardingViewerService $onboarding,
        private readonly SupportAndroidRuntimeViewerService $androidRuntime,
    ) {}

    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WATCH = 'watch';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_CRITICAL = 'critical';

    /** Worst-last ordering used to escalate the overall status. */
    private const RANK = [
        self::STATUS_HEALTHY => 0,
        self::STATUS_WATCH => 1,
        self::STATUS_DEGRADED => 2,
        self::STATUS_BLOCKED => 3,
        self::STATUS_CRITICAL => 4,
    ];

    /**
     * @return array<string, mixed>
     */
    public function overview(Tenant $tenant): array
    {
        $policy = (array) config('support_operations_governance.health_policy', []);
        $reasons = [];
        $status = self::STATUS_HEALTHY;

        // SUP-R014 — manual suspension always wins.
        $suspended = $tenant->activeManualSuspension() !== null;
        if ($suspended) {
            $status = $this->worst($status, (string) ($policy['manual_suspension_status'] ?? self::STATUS_CRITICAL));
            $reasons[] = 'manual_suspension_active';
        }

        // Billing / collection.
        $graceDays = (int) ($policy['grace_days'] ?? 7);
        $now = Carbon::now();
        $pastGrace = false;
        $unpaidInGrace = false;
        foreach (TenantBillingInvoice::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(20)->get() as $invoice) {
            $unpaidState = in_array($invoice->collection_state, [
                TenantBillingInvoice::COLLECTION_OVERDUE,
                TenantBillingInvoice::COLLECTION_FAILED,
            ], true);
            if ($unpaidState) {
                $dueAt = $invoice->due_at;
                if ($dueAt !== null && $dueAt->copy()->addDays($graceDays)->isPast($now)) {
                    $pastGrace = true;
                } else {
                    $unpaidInGrace = true;
                }
            } elseif ($invoice->collection_state === TenantBillingInvoice::COLLECTION_PENDING) {
                $unpaidInGrace = true;
            }
        }
        if ($pastGrace) {
            $status = $this->worst($status, (string) ($policy['unpaid_past_grace_status'] ?? self::STATUS_BLOCKED));
            $reasons[] = 'unpaid_past_grace';
        } elseif ($unpaidInGrace) {
            $status = $this->worst($status, (string) ($policy['unpaid_in_grace_status'] ?? self::STATUS_DEGRADED));
            $reasons[] = 'unpaid_in_grace';
        }

        // Onboarding.
        $latestRun = TenantProvisioningRun::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->first();
        if ($latestRun !== null && in_array($latestRun->status, [
            TenantProvisioningRun::STATUS_FAILED,
            TenantProvisioningRun::STATUS_CANCELLED,
        ], true)) {
            $status = $this->worst($status, (string) ($policy['onboarding_failed_status'] ?? self::STATUS_DEGRADED));
            $reasons[] = 'onboarding_'.$latestRun->status;
        }

        // Device / sync (Sprint 34).
        $runtime = $this->androidRuntime->summary($tenant->id);
        if (($runtime['revoked_device_count'] ?? 0) > 0) {
            $status = $this->worst($status, (string) ($policy['revoked_device_status'] ?? self::STATUS_WATCH));
            $reasons[] = 'revoked_device_present';
        }
        $syncFailures = $this->androidRuntime->syncFailures($tenant->id);
        if (($syncFailures['failed_batch_count'] ?? 0) > 0 || ($syncFailures['failed_item_count'] ?? 0) > 0) {
            $status = $this->worst($status, (string) ($policy['sync_failure_status'] ?? self::STATUS_WATCH));
            $reasons[] = 'sync_failures_present';
        }

        if ($reasons === []) {
            $reasons[] = 'no_issues_detected';
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_code' => $tenant->code,
            'tenant_status' => $tenant->status,
            'manual_suspension_active' => $suspended,
            'health_status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'dimensions' => [
                'billing' => $this->billing->summary($tenant->id),
                'payment' => $this->payment->summary($tenant->id),
                'entitlement' => $this->entitlement->summary($tenant->id),
                'onboarding' => $this->onboarding->summary($tenant->id),
                'android_runtime' => $runtime,
            ],
        ];
    }

    /**
     * A lightweight health summary (no nested dimensions) for the tenant list.
     *
     * @return array<string, mixed>
     */
    public function briefStatus(Tenant $tenant): array
    {
        $overview = $this->overview($tenant);

        return [
            'tenant_id' => $overview['tenant_id'],
            'tenant_code' => $overview['tenant_code'],
            'tenant_status' => $overview['tenant_status'],
            'manual_suspension_active' => $overview['manual_suspension_active'],
            'health_status' => $overview['health_status'],
            'reason_codes' => $overview['reason_codes'],
        ];
    }

    private function worst(string $a, string $b): string
    {
        $ra = self::RANK[$a] ?? 0;
        $rb = self::RANK[$b] ?? 0;

        return $ra >= $rb ? $a : $b;
    }
}
