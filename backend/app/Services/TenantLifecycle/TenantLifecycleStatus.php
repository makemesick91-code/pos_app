<?php

namespace App\Services\TenantLifecycle;

/**
 * Sprint 25 — the canonical tenant lifecycle status vocabulary.
 *
 * This is the single server-side source of truth for the set of lifecycle
 * statuses (TLS-R001). Statuses are computed only by TenantLifecycleService and
 * are never trusted from client input. The blocked set is what the runtime
 * lifecycle guard denies operational access for.
 */
final class TenantLifecycleStatus
{
    public const ONBOARDING = 'onboarding';
    public const ACTIVE = 'active';
    public const GRACE = 'grace';
    public const PAST_DUE = 'past_due';
    public const SUSPENDED = 'suspended';
    public const CANCELLED = 'cancelled';
    public const ARCHIVED = 'archived';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ONBOARDING,
            self::ACTIVE,
            self::GRACE,
            self::PAST_DUE,
            self::SUSPENDED,
            self::CANCELLED,
            self::ARCHIVED,
        ];
    }

    /**
     * Lifecycle statuses that deny operational (POS) access at the guard.
     *
     * @return array<int, string>
     */
    public static function blocked(): array
    {
        return [
            self::SUSPENDED,
            self::CANCELLED,
            self::ARCHIVED,
        ];
    }

    public static function isBlocked(string $status): bool
    {
        return in_array($status, self::blocked(), true);
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
