<?php

namespace App\Services\Entitlements;

/**
 * Sprint 32 — redacts entitlement decision metadata before it is persisted or
 * returned (ENT-R020). Mirrors the Sprint 30/31 redaction contract: any key that
 * looks like a secret/credential/token/PII field is dropped, non-scalar leaves
 * that could smuggle a raw payload are dropped, and long strings are truncated.
 *
 * The entitlement surface therefore can never leak secrets, signatures, tokens,
 * or customer PII (phone/email/name/card/KTP/NIK) into the audit trail, API,
 * command output, smoke output, or docs.
 */
class EntitlementRedactor
{
    /** Case-insensitive substrings that force a key to be dropped. */
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
        'card',
        'cvv',
        'pan',
        'ktp',
        'nik',
        'phone',
        'email',
        'owner_name',
        'customer',
    ];

    private const MAX_STRING = 300;

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public function redact(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($this->isRedactedKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                // Drop nested structures entirely — a raw payload can hide there.
                continue;
            }

            if (is_string($value) && strlen($value) > self::MAX_STRING) {
                $value = substr($value, 0, self::MAX_STRING);
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isRedactedKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
