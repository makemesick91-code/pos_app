<?php

namespace App\Services\Admin;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantSupportIncident;
use App\Services\AndroidRuntime\AndroidRuntimeSummaryService;
use App\Services\Billing\BillingSummaryService;
use App\Services\Observability\ObservabilityHealthService;
use App\Services\Observability\QueueHealthService;
use App\Services\PaymentGateway\PaymentGatewaySummaryService;
use App\Services\TenantLifecycle\TenantSuspensionSummaryService;
use App\Services\TenantOnboarding\OnboardingSummaryService;
use Illuminate\Support\Facades\DB;

/**
 * UIX-3 — assembles the SaaS Control Center dashboard from EXISTING governed
 * read services. It computes NO business status of its own: lifecycle,
 * entitlement, billing and usage numbers all come from their canonical summary
 * services (which already apply their domain redactors).
 *
 * Truthfulness contract (UIX-R024/R025): every metric group is resolved inside
 * its own guard. If a source service is unavailable or errors, that group is
 * reported as {available:false} — the UI renders an explicit "unavailable"
 * state rather than a misleading zero. Aggregate queries are bounded (grouped
 * counts, no per-tenant fan-out) so the dashboard never degrades into N+1.
 */
class ControlCenterMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'tenants' => $this->tenants(),
            'trials' => $this->trials(),
            'billing' => $this->billing(),
            'settlement' => $this->settlement(),
            'devices' => $this->devices(),
            'outlets' => $this->outlets(),
            'support' => $this->support(),
            'queue' => $this->queue(),
            'health' => $this->health(),
        ];
    }

    /**
     * Coarse platform-wide tenant counts via a single grouped query, enriched
     * with the authoritative manual-suspension count. This is bounded and does
     * NOT recompute per-tenant lifecycle (that happens on the paginated list).
     *
     * @return array<string, mixed>
     */
    private function tenants(): array
    {
        return $this->guard(function (): array {
            $byStatus = Tenant::query()
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all();

            $byStatus = array_change_key_case(array_map('intval', $byStatus));

            $manualSuspended = null;
            try {
                $manualSuspended = (int) (app(TenantSuspensionSummaryService::class)
                    ->summary()['suspended_tenants'] ?? 0);
            } catch (\Throwable) {
                $manualSuspended = null;
            }

            return [
                'total' => (int) Tenant::query()->count(),
                'active' => (int) ($byStatus['active'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
                'inactive' => (int) ($byStatus['inactive'] ?? 0),
                'manual_suspensions_active' => $manualSuspended,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function trials(): array
    {
        return $this->guard(function (): array {
            $summary = app(OnboardingSummaryService::class)->trialSummary();

            return [
                'runs_total' => (int) ($summary['total_runs'] ?? 0),
                'trials_total' => (int) ($summary['trials_total'] ?? 0),
                'trials_active' => (int) ($summary['trials_active'] ?? 0),
                'trials_expired' => (int) ($summary['trials_expired'] ?? 0),
                'by_status' => $summary['by_status'] ?? [],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function billing(): array
    {
        return $this->guard(function (): array {
            $summary = app(BillingSummaryService::class)->invoiceSummary();
            $byStatus = (array) ($summary['by_status'] ?? []);

            // Sum the statuses that represent money still owed. We do not invent
            // a status: we match only against the keys the summary actually
            // returned, so an empty/unknown set yields an honest 0-of-known.
            $attention = 0;
            foreach ($byStatus as $status => $count) {
                $key = strtolower((string) $status);
                if (str_contains($key, 'overdue')
                    || str_contains($key, 'past_due')
                    || str_contains($key, 'unpaid')
                    || str_contains($key, 'failed')
                    || str_contains($key, 'pending')) {
                    $attention += (int) $count;
                }
            }

            return [
                'total_invoices' => (int) ($summary['total_invoices'] ?? 0),
                'total_amount' => (int) ($summary['total_amount'] ?? 0),
                'attention_invoices' => $attention,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function settlement(): array
    {
        return $this->guard(function (): array {
            $summary = app(PaymentGatewaySummaryService::class)->settlementSummary();

            return [
                'settled_intents' => (int) ($summary['settled_intents'] ?? 0),
                'settled_amount' => (int) ($summary['settled_amount'] ?? 0),
                'open_intents' => (int) ($summary['open_intents'] ?? 0),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function devices(): array
    {
        return $this->guard(function (): array {
            $summary = app(AndroidRuntimeSummaryService::class)->deviceSummary();
            $byStatus = array_change_key_case((array) ($summary['by_status'] ?? []));

            return [
                'total' => (int) ($summary['total'] ?? 0),
                'active' => (int) ($byStatus['active'] ?? 0),
                'revoked' => (int) ($byStatus['revoked'] ?? 0),
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function outlets(): array
    {
        return $this->guard(fn (): array => [
            'total' => (int) Store::query()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function support(): array
    {
        return $this->guard(fn (): array => [
            'open_incidents' => (int) TenantSupportIncident::query()
                ->whereNotIn('status', TenantSupportIncident::TERMINAL_STATUSES)
                ->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        return $this->guard(function (): array {
            $summary = app(QueueHealthService::class)->summary();
            $metrics = (array) ($summary['metrics'] ?? []);

            return [
                'status' => (string) ($summary['status'] ?? 'unknown'),
                'reason_codes' => array_values((array) ($summary['reason_codes'] ?? [])),
                'pending_jobs' => (int) ($metrics['pending_jobs'] ?? 0),
                'failed_jobs' => (int) ($metrics['failed_jobs'] ?? 0),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        return $this->guard(function (): array {
            $overview = app(ObservabilityHealthService::class)->overview();

            return [
                'status' => (string) ($overview['status'] ?? 'unknown'),
                'reason_codes' => array_values((array) ($overview['reason_codes'] ?? [])),
            ];
        });
    }

    /**
     * Resolve one metric group, degrading to an explicit unavailable state on
     * any error so the dashboard never fabricates a number.
     *
     * @param  callable():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function guard(callable $resolver): array
    {
        try {
            return ['available' => true] + $resolver();
        } catch (\Throwable) {
            return ['available' => false];
        }
    }
}
