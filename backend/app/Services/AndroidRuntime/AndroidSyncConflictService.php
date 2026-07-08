<?php

namespace App\Services\AndroidRuntime;

/**
 * Sprint 34 — deterministic, explainable conflict decisions (ADR-R016).
 *
 * Maps a stable conflict code to a safe message from config. Every code is known
 * ahead of time (config android_runtime_governance.conflict_codes); an unknown
 * code degrades to `invalid_payload`. Explanations never contain PII.
 */
class AndroidSyncConflictService
{
    public const CODE_DUPLICATE = 'duplicate_client_item';
    public const CODE_STALE_CATALOG = 'stale_catalog_version';
    public const CODE_STALE_PRICE = 'stale_price_snapshot';
    public const CODE_REGISTER_MISMATCH = 'register_mismatch';
    public const CODE_DEVICE_REVOKED = 'device_revoked';
    public const CODE_TENANT_READ_ONLY = 'tenant_read_only';
    public const CODE_TENANT_SUSPENDED = 'tenant_suspended';
    public const CODE_UNPAID_PAST_GRACE = 'unpaid_past_grace';
    public const CODE_TRIAL_EXPIRED = 'trial_expired';
    public const CODE_ENTITLEMENT_DENIED = 'entitlement_denied';
    public const CODE_INVALID_PAYLOAD = 'invalid_payload';

    /**
     * @return array<string, string>
     */
    public function codes(): array
    {
        return (array) config('android_runtime_governance.conflict_codes', []);
    }

    public function isKnown(string $code): bool
    {
        return array_key_exists($code, $this->codes());
    }

    public function normalize(string $code): string
    {
        return $this->isKnown($code) ? $code : self::CODE_INVALID_PAYLOAD;
    }

    /**
     * A safe, deterministic explanation for a conflict code.
     *
     * @return array{code: string, message: string}
     */
    public function explain(string $code): array
    {
        $code = $this->normalize($code);

        return [
            'code' => $code,
            'message' => (string) ($this->codes()[$code] ?? 'Conflict.'),
        ];
    }
}
