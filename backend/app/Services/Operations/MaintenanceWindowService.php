<?php

namespace App\Services\Operations;

use App\Models\ProductionMaintenanceWindow;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 19 — production maintenance window lifecycle & governance.
 *
 * Owns create/update/status-transition for maintenance windows and evaluates the
 * governance state of active windows: a HIGH/CRITICAL window without a rollback
 * plan reference forces WATCH/NO_GO. A maintenance window record never performs a
 * deployment and never stores credentials (secret-looking metadata is stripped).
 */
class MaintenanceWindowService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    private const REDACTED_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'private_key',
        'server_key', 'client_secret', 'authorization', 'credential',
    ];

    private const REDACTION = '[REDACTED]';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): ProductionMaintenanceWindow
    {
        $risk = $this->normalizeRisk((string) ($attributes['risk_level'] ?? ProductionMaintenanceWindow::RISK_LOW));

        return ProductionMaintenanceWindow::query()->create([
            'maintenance_reference' => (string) ($attributes['maintenance_reference'] ?? $this->generateReference()),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? ProductionMaintenanceWindow::STATUS_PLANNED)),
            'title' => $this->sanitizeString((string) ($attributes['title'] ?? 'Untitled maintenance')),
            'description' => isset($attributes['description']) ? $this->sanitizeString((string) $attributes['description']) : null,
            'scheduled_start_at' => Carbon::parse($attributes['scheduled_start_at']),
            'scheduled_end_at' => Carbon::parse($attributes['scheduled_end_at']),
            'risk_level' => $risk,
            'owner_user_id' => $attributes['owner_user_id'] ?? $actor?->id,
            'rollback_plan_reference' => $attributes['rollback_plan_reference'] ?? null,
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(ProductionMaintenanceWindow $window, array $attributes, ?User $actor = null): ProductionMaintenanceWindow
    {
        $map = [
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $v === null ? null : $this->sanitizeString((string) $v),
            'scheduled_start_at' => fn ($v) => Carbon::parse($v),
            'scheduled_end_at' => fn ($v) => Carbon::parse($v),
            'actual_start_at' => fn ($v) => $v === null ? null : Carbon::parse($v),
            'actual_end_at' => fn ($v) => $v === null ? null : Carbon::parse($v),
            'risk_level' => fn ($v) => $this->normalizeRisk((string) $v),
            'owner_user_id' => fn ($v) => $v,
            'rollback_plan_reference' => fn ($v) => $v,
            'evidence_reference' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $window->{$key} = $caster($attributes[$key]);
            }
        }

        $window->save();

        return $window->refresh();
    }

    /**
     * Transition status; IN_PROGRESS/COMPLETED stamp actual start/end when unset.
     */
    public function transitionStatus(ProductionMaintenanceWindow $window, string $status, ?User $actor = null, ?Carbon $now = null): ProductionMaintenanceWindow
    {
        $now ??= Carbon::now();
        $status = $this->normalizeStatus($status);
        $window->status = $status;

        match ($status) {
            ProductionMaintenanceWindow::STATUS_IN_PROGRESS => $window->actual_start_at = $window->actual_start_at ?? $now,
            ProductionMaintenanceWindow::STATUS_COMPLETED => $window->actual_end_at = $window->actual_end_at ?? $now,
            default => null,
        };

        $window->save();

        return $window->refresh();
    }

    /**
     * Evaluate governance of active maintenance windows.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $highRiskLevels = (array) config('production_operations.high_risk_maintenance_levels', []);

        $active = ProductionMaintenanceWindow::query()->active()->get();

        $highRiskWithoutRollback = $active
            ->filter(fn (ProductionMaintenanceWindow $w) => in_array($w->risk_level, $highRiskLevels, true) && ! $w->hasRollbackPlan())
            ->count();

        $highRiskWithRollback = $active
            ->filter(fn (ProductionMaintenanceWindow $w) => in_array($w->risk_level, $highRiskLevels, true) && $w->hasRollbackPlan())
            ->count();

        $blocked = $active->where('status', ProductionMaintenanceWindow::STATUS_BLOCKED)->count();

        $decision = self::DECISION_GO;
        if ($highRiskWithoutRollback > 0) {
            $decision = self::DECISION_NO_GO;
        } elseif ($highRiskWithRollback > 0 || $blocked > 0) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'counts' => [
                'active_total' => $active->count(),
                'high_risk_without_rollback' => $highRiskWithoutRollback,
                'high_risk_with_rollback' => $highRiskWithRollback,
                'blocked' => $blocked,
            ],
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, ProductionMaintenanceWindow::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid maintenance status: {$status}");
        }

        return $status;
    }

    private function normalizeRisk(string $risk): string
    {
        $risk = strtoupper(trim($risk));
        if (! in_array($risk, ProductionMaintenanceWindow::RISK_LEVELS, true)) {
            throw new InvalidArgumentException("Invalid risk level: {$risk}");
        }

        return $risk;
    }

    private function generateReference(): string
    {
        return 'MNT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function sanitizeString(string $value): string
    {
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            $value = (string) preg_replace(
                '/('.preg_quote($fragment, '/').'\s*[:=]\s*)\S+/i',
                '$1'.self::REDACTION,
                $value,
            );
        }

        return $value;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>|null
     */
    private function sanitizeArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                $clean[$key] = self::REDACTION;

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$key] = $this->sanitizeString($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
