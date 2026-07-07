<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sprint 11 — records platform admin actions to the admin_audit_logs table.
 *
 * Every mutation performed by a platform admin (subscription assign/update,
 * device revoke, plan create/update/deactivate) flows through here. before/after
 * snapshots are sanitized before persistence: secret-looking keys and raw
 * gateway payloads are stripped so the audit trail can never leak credentials.
 */
class AdminAuditLogger
{
    /**
     * Keys that must never be persisted in an audit snapshot, regardless of the
     * caller. Matched case-insensitively as substrings.
     */
    private const REDACTED_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'signature',
        'gateway_payload',
        'raw_payload',
        'payload',
        'credential',
        'server_key',
        'client_key',
        'webhook',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        User $actor,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?int $tenantId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AdminAuditLog {
        return AdminAuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'tenant_id' => $tenantId,
            'before_values' => $this->sanitize($before),
            'after_values' => $this->sanitize($after),
            'metadata' => $this->sanitize($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /**
     * Strip secret-looking keys and drop non-scalar leaves that could smuggle a
     * raw gateway payload. Returns null for empty input.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $value) {
            if ($this->isRedactedKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitize($value);

                if ($nested !== null && $nested !== []) {
                    $clean[$key] = $nested;
                }

                continue;
            }

            // Only keep safe scalar leaves (and stringified dates).
            if ($value === null || is_scalar($value)) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = (string) $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function isRedactedKey(string $key): bool
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
