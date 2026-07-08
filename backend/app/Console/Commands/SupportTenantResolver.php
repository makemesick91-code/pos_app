<?php

namespace App\Console\Commands;

use App\Models\Tenant;

/**
 * Sprint 35 — resolves the optional `--tenant` CLI option (id or code) to a
 * Tenant for the support-ops read commands. Returns null when absent/not found so
 * a command can print a safe hint and still exit cleanly (CI-safe on an empty DB).
 */
class SupportTenantResolver
{
    public static function resolve(?string $value): ?Tenant
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? Tenant::query()->find((int) $value)
            : Tenant::query()->where('code', $value)->first();
    }
}
