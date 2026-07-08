<?php

namespace App\Services\Billing;

/**
 * Sprint 30 — redacts invoice/payment metadata before persistence (BIL-R006).
 *
 * Any key whose name looks like a secret/credential/token is dropped, and long
 * strings are truncated so raw payloads or excessive PII cannot be smuggled into
 * the billing tables. Mirrors the AdminAuditLogger redaction contract so the
 * billing surface can never leak credentials.
 */
class BillingMetadataSanitizer
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
    ];

    private const MAX_STRING = 500;

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $clean = [];
        foreach ($metadata as $key => $value) {
            if ($this->isRedactedKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);

                continue;
            }

            if (is_string($value) && strlen($value) > self::MAX_STRING) {
                $clean[$key] = substr($value, 0, self::MAX_STRING);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function isRedactedKey(string $key): bool
    {
        $needle = strtolower($key);
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($needle, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
