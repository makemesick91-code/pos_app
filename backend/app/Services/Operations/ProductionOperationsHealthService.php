<?php

namespace App\Services\Operations;

use App\Models\ProductionOperationRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sprint 19 — production operations health evaluation.
 *
 * Aggregates the required production health signals (backend/auth/tenant/product
 * sync/cashier/QRIS/offline-sync/receipt/inventory/reports-closing/subscription/
 * admin-onboarding plus the backup-restore/support-SLA/release-rollback readiness
 * signals) into a secret-safe PASS/WARN/FAIL report and a GO/WATCH/NO_GO
 * decision. A critical signal FAIL forces NO_GO; a non-critical WARN forces
 * WATCH; all PASS is GO.
 *
 * Signals are derived from the persisted schema contract and the governance
 * services — never by running real business traffic. Recording an operation run
 * never deploys, never runs real backup/restore, and never sends real alerts.
 */
class ProductionOperationsHealthService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(
        private readonly BackupRestoreGovernanceService $backup,
        private readonly SupportSlaGovernanceService $supportSla,
        private readonly ReleaseRollbackGovernanceService $releaseRollback,
        private readonly ProductionIncidentService $incidents,
    ) {}

    /**
     * Evaluate every required health signal and produce the aggregate decision.
     *
     * @return array<string,mixed>
     */
    public function evaluate(?Carbon $now = null): array
    {
        $signals = $this->signals($now);

        return [
            'decision' => $this->decide($signals),
            'signals' => $signals,
        ];
    }

    /**
     * @return array<int,array{key:string,status:string,critical:bool,message:string}>
     */
    public function signals(?Carbon $now = null): array
    {
        $required = (array) config('production_operations.required_health_signals', []);
        $critical = (array) config('production_operations.critical_health_signals', []);

        $backup = $this->backup->evaluate();
        $supportSla = $this->supportSla->evaluate($now);
        $releaseRollback = $this->releaseRollback->evaluate();

        $signals = [];
        foreach ($required as $key) {
            $status = match ($key) {
                'backup_restore_readiness' => $this->fromDecision((string) $backup['decision']),
                'support_sla_readiness' => $this->fromDecision((string) $supportSla['decision']),
                'release_rollback_readiness' => $this->fromDecision((string) $releaseRollback['decision']),
                default => $this->schemaSignal($key),
            };

            $signals[] = [
                'key' => $key,
                'status' => $status,
                'critical' => in_array($key, $critical, true),
                'message' => $this->message($key, $status),
            ];
        }

        return $signals;
    }

    /**
     * Critical FAIL = NO_GO, any FAIL/WARN = WATCH, all PASS = GO.
     *
     * @param array<int,array{status:string,critical:bool}> $signals
     */
    public function decide(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL && ($signal['critical'] ?? false)) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if (in_array($signal['status'], [self::STATUS_FAIL, self::STATUS_WARN], true)) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /**
     * Persist an operation run from the current health/governance evaluation.
     *
     * @param array<string,mixed> $attributes
     */
    public function createRun(array $attributes, ?User $actor = null, ?Carbon $now = null): ProductionOperationRun
    {
        $health = $this->evaluate($now);
        $incidentSummary = $this->incidents->summary($now);
        $decision = $this->worst([
            $health['decision'],
            (string) $incidentSummary['decision'],
        ]);

        return ProductionOperationRun::query()->create([
            'operation_reference' => (string) ($attributes['operation_reference'] ?? $this->generateReference()),
            'status' => ProductionOperationRun::STATUS_REVIEW,
            'decision' => $decision,
            'window_start' => $attributes['window_start'] ?? null,
            'window_end' => $attributes['window_end'] ?? null,
            'health_signals' => $health['signals'],
            'incident_summary' => $incidentSummary,
            'backup_restore_summary' => $this->backup->evaluate(),
            'support_sla_summary' => $this->supportSla->evaluate($now),
            'maintenance_summary' => $attributes['maintenance_summary'] ?? null,
            'release_rollback_summary' => $this->releaseRollback->evaluate(),
            'evidence_references' => $attributes['evidence_references'] ?? null,
            'created_by' => $actor?->id,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function approve(ProductionOperationRun $run, ?User $actor = null): ProductionOperationRun
    {
        $run->status = match ($run->decision) {
            ProductionOperationRun::DECISION_NO_GO => ProductionOperationRun::STATUS_BLOCKED,
            ProductionOperationRun::DECISION_WATCH => ProductionOperationRun::STATUS_WATCH,
            default => ProductionOperationRun::STATUS_HEALTHY,
        };
        $run->approved_by = $actor?->id;
        $run->approved_at = Carbon::now();
        $run->save();

        return $run->refresh();
    }

    public function block(ProductionOperationRun $run, ?User $actor = null): ProductionOperationRun
    {
        $run->status = ProductionOperationRun::STATUS_BLOCKED;
        $run->save();

        return $run->refresh();
    }

    private function schemaSignal(string $key): string
    {
        // Map each business/operations health signal to the persisted schema
        // contract that must exist for that capability to be operable. A missing
        // table is a WARN (dev/CI may not have migrated) rather than a hard FAIL.
        $table = match ($key) {
            'backend_health' => 'users',
            'auth_login' => 'personal_access_tokens',
            'tenant_context' => 'tenants',
            'product_sync' => 'products',
            'cashier_cash_sale' => 'sales',
            'qris_payment_status' => 'payments',
            'offline_sync_queue' => 'sales',
            'receipt_printer' => 'sales',
            'inventory_movement' => 'inventory_movements',
            'reports_closing' => 'daily_closings',
            'subscription_device' => 'registered_devices',
            'admin_onboarding' => 'tenant_onboarding_runs',
            default => null,
        };

        if ($table === null) {
            return self::STATUS_PASS;
        }

        return Schema::hasTable($table) ? self::STATUS_PASS : self::STATUS_WARN;
    }

    private function fromDecision(string $decision): string
    {
        return match ($decision) {
            self::DECISION_NO_GO => self::STATUS_FAIL,
            self::DECISION_WATCH => self::STATUS_WARN,
            default => self::STATUS_PASS,
        };
    }

    private function message(string $key, string $status): string
    {
        return match ($status) {
            self::STATUS_FAIL => "{$key} signal is FAIL.",
            self::STATUS_WARN => "{$key} signal has a warning.",
            default => "{$key} signal is healthy.",
        };
    }

    /**
     * @param array<int,string> $decisions
     */
    private function worst(array $decisions): string
    {
        if (in_array(self::DECISION_NO_GO, $decisions, true)) {
            return self::DECISION_NO_GO;
        }

        if (in_array(self::DECISION_WATCH, $decisions, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    private function generateReference(): string
    {
        return 'OPS-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
