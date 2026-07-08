<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Services\UsageEventLedger\SanitizesUsageEventMetadata;

/**
 * Sprint 28 — read-only anomaly detector for the append-only usage event ledger
 * (ULR-R001, ULR-R002). It NEVER mutates ledger data — it only reads via the
 * repository and emits already-redacted {@see UsageLedgerAnomaly} objects
 * (ULR-R006). Detection can be scoped by tenant, meter, and severity so both the
 * CLI and the platform-admin API can reuse one authoritative scan.
 *
 * Only duplicate double-count drift is marked auto-repairable; missing fields,
 * invalid quantities/periods, unknown meters, and suspicious metadata are
 * reported for manual review only and are never auto-mutated (ULR-R010).
 */
class UsageLedgerAnomalyDetector
{
    use SanitizesUsageEventMetadata;

    public function __construct(
        private readonly UsageLedgerAnomalyRepository $repository,
    ) {}

    /**
     * @return array<int, UsageLedgerAnomaly>
     */
    public function scan(?int $tenantId = null, ?string $meterKey = null, ?string $severity = null): array
    {
        $anomalies = [
            ...$this->detectDuplicates($tenantId, $meterKey),
            ...$this->detectMissingFields($tenantId, $meterKey),
            ...$this->detectInvalidQuantity($tenantId, $meterKey),
            ...$this->detectInvalidPeriods($tenantId, $meterKey),
            ...$this->detectUnknownMeters($tenantId, $meterKey),
            ...$this->detectSuspiciousMetadata($tenantId, $meterKey),
        ];

        if ($severity !== null && UsageLedgerAnomalySeverity::isValid($severity)) {
            $anomalies = array_values(array_filter(
                $anomalies,
                fn (UsageLedgerAnomaly $a) => $a->severity === $severity,
            ));
        }

        usort(
            $anomalies,
            fn (UsageLedgerAnomaly $a, UsageLedgerAnomaly $b) => UsageLedgerAnomalySeverity::rank($b->severity)
                <=> UsageLedgerAnomalySeverity::rank($a->severity),
        );

        return $anomalies;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectDuplicates(?int $tenantId, ?string $meterKey): array
    {
        $out = [];
        foreach ($this->repository->duplicateFingerprintGroups($tenantId, $meterKey) as $group) {
            $events = (int) $group->events;
            $quantity = (int) $group->quantity;
            // Collapse the fingerprint group to a single logical event. Every usage
            // event carries quantity 1, so the extra count is quantity - 1.
            $delta = -max(0, $quantity - 1);

            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_DUPLICATE_IDEMPOTENCY,
                severity: UsageLedgerAnomalySeverity::CRITICAL,
                tenantId: (int) $group->tenant_id,
                meterKey: (string) $group->meter_key,
                periodKey: (string) $group->period_key,
                summary: "Duplicate double-count drift: {$events} events collapse to 1 (meter inflated by ".abs($delta).').',
                context: [
                    'events' => $events,
                    'quantity' => $quantity,
                    'keep_event_id' => (int) $group->keep_id,
                    'fingerprint' => substr((string) $group->request_fingerprint, 0, 12).'…',
                ],
                autoRepairable: true,
                repairType: \App\Models\TenantUsageLedgerRepair::TYPE_DUPLICATE_USAGE_CORRECTION,
                quantityDelta: $delta,
                signature: 'dupe:'.$group->tenant_id.':'.$group->meter_key.':'.$group->period_key.':'
                    .substr((string) $group->request_fingerprint, 0, 24),
            );
        }

        return $out;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectMissingFields(?int $tenantId, ?string $meterKey): array
    {
        $out = [];
        foreach ($this->repository->missingRequiredFieldEvents($tenantId, $meterKey) as $event) {
            $missing = [];
            foreach (['event_key', 'event_category', 'period_key', 'occurred_at'] as $field) {
                $value = $event->getAttribute($field);
                if ($value === null || $value === '') {
                    $missing[] = $field;
                }
            }
            if ($event->getAttribute('meter_key') === '') {
                $missing[] = 'meter_key';
            }
            if ($missing === []) {
                continue;
            }

            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_MISSING_REQUIRED_FIELD,
                severity: UsageLedgerAnomalySeverity::WARNING,
                tenantId: (int) $event->tenant_id,
                meterKey: $event->meter_key === null ? null : (string) $event->meter_key,
                periodKey: $event->period_key === null ? null : (string) $event->period_key,
                summary: 'Usage event is missing required field(s): '.implode(', ', $missing).'.',
                context: ['event_id' => (int) $event->id, 'missing' => $missing],
                autoRepairable: false,
            );
        }

        return $out;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectInvalidQuantity(?int $tenantId, ?string $meterKey): array
    {
        $out = [];
        foreach ($this->repository->invalidQuantityEvents($tenantId, $meterKey) as $event) {
            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_INVALID_QUANTITY,
                severity: UsageLedgerAnomalySeverity::WARNING,
                tenantId: (int) $event->tenant_id,
                meterKey: $event->meter_key === null ? null : (string) $event->meter_key,
                periodKey: $event->period_key === null ? null : (string) $event->period_key,
                summary: 'Usage event has a non-positive quantity ('.(int) $event->quantity.').',
                context: ['event_id' => (int) $event->id, 'quantity' => (int) $event->quantity],
                autoRepairable: false,
            );
        }

        return $out;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectInvalidPeriods(?int $tenantId, ?string $meterKey): array
    {
        $registry = (array) config('tenant_plan.usage_limits', []);
        $out = [];

        foreach ($this->repository->meteredEvents($tenantId, $meterKey) as $event) {
            $key = (string) $event->meter_key;
            $meta = (array) ($registry[$key] ?? []);
            $period = (string) ($meta['period'] ?? 'lifetime');
            if ($period !== 'monthly') {
                continue;
            }

            $periodKey = (string) $event->period_key;
            $occurred = $event->occurred_at;
            $problems = [];
            if (preg_match('/^\d{4}-\d{2}$/', $periodKey) !== 1) {
                $problems[] = 'period_key not in Y-m format';
            } elseif ($occurred !== null && $occurred->format('Y-m') !== $periodKey) {
                $problems[] = 'occurred_at ('.$occurred->format('Y-m').') does not match period_key';
            }
            if ($problems === []) {
                continue;
            }

            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_INVALID_PERIOD,
                severity: UsageLedgerAnomalySeverity::WARNING,
                tenantId: (int) $event->tenant_id,
                meterKey: $key,
                periodKey: $periodKey,
                summary: 'Invalid monthly period on usage event: '.implode('; ', $problems).'.',
                context: ['event_id' => (int) $event->id, 'problems' => $problems],
                autoRepairable: false,
            );
        }

        return $out;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectUnknownMeters(?int $tenantId, ?string $meterKey): array
    {
        $registry = array_keys((array) config('tenant_plan.usage_limits', []));
        $out = [];

        foreach ($this->repository->distinctMeterKeys($tenantId, $meterKey) as $key) {
            if (in_array($key, $registry, true)) {
                continue;
            }
            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_UNKNOWN_METER,
                severity: UsageLedgerAnomalySeverity::WARNING,
                tenantId: $tenantId,
                meterKey: $key,
                periodKey: null,
                summary: "Unknown meter key '{$key}' is not in the canonical usage limit registry.",
                context: ['meter_key' => $key],
                autoRepairable: false,
            );
        }

        return $out;
    }

    /** @return array<int, UsageLedgerAnomaly> */
    private function detectSuspiciousMetadata(?int $tenantId, ?string $meterKey): array
    {
        $out = [];
        foreach ($this->repository->suspiciousMetadataEvents($tenantId, $meterKey) as $event) {
            $offendingKeys = $this->offendingMetadataKeys((array) ($event->metadata ?? []));
            if ($offendingKeys === []) {
                continue;
            }

            $out[] = new UsageLedgerAnomaly(
                type: UsageLedgerAnomaly::TYPE_UNSANITIZED_METADATA,
                severity: UsageLedgerAnomalySeverity::CRITICAL,
                tenantId: (int) $event->tenant_id,
                meterKey: $event->meter_key === null ? null : (string) $event->meter_key,
                periodKey: $event->period_key === null ? null : (string) $event->period_key,
                // Never print the value — only that a secret-looking key is present.
                summary: 'Usage event metadata contains secret-looking key(s): '.implode(', ', $offendingKeys).'.',
                context: ['event_id' => (int) $event->id, 'offending_keys' => $offendingKeys],
                autoRepairable: false,
            );
        }

        return $out;
    }

    /**
     * Return the redacted list of metadata key names that look like secrets. Never
     * returns any value (ULR-R006).
     *
     * @param array<int|string, mixed> $metadata
     * @return array<int, string>
     */
    private function offendingMetadataKeys(array $metadata): array
    {
        $found = [];
        $walk = function (array $data) use (&$walk, &$found): void {
            foreach ($data as $key => $value) {
                $lower = strtolower((string) $key);
                foreach (UsageLedgerAnomalyRepository::DANGEROUS_METADATA_FRAGMENTS as $fragment) {
                    if (str_contains($lower, $fragment)) {
                        $found[] = (string) $key;
                        break;
                    }
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($metadata);

        return array_values(array_unique($found));
    }
}
