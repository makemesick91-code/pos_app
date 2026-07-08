<?php

namespace App\Services\UsageLedgerAnomaly;

/**
 * Sprint 28 — anomaly severity vocabulary (ULR-R001..R005).
 *
 * critical — usage counts are provably wrong or a secret may be exposed; blocks a
 * clean anomaly-scan unless explicitly allowed. warning — a data-quality issue
 * that needs manual review but does not by itself corrupt a live meter. info —
 * observational only.
 */
final class UsageLedgerAnomalySeverity
{
    public const CRITICAL = 'critical';
    public const WARNING = 'warning';
    public const INFO = 'info';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::CRITICAL, self::WARNING, self::INFO];
    }

    public static function isValid(string $severity): bool
    {
        return in_array($severity, self::all(), true);
    }

    /** Higher rank = more severe. */
    public static function rank(string $severity): int
    {
        return match ($severity) {
            self::CRITICAL => 3,
            self::WARNING => 2,
            self::INFO => 1,
            default => 0,
        };
    }
}
