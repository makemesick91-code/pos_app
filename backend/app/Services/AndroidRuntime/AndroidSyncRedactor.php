<?php

namespace App\Services\AndroidRuntime;

/**
 * Sprint 34 — redacts any metadata before it is persisted to a sync/activation
 * trace, returned by an API/command, or written to a log (ADR-R020/R021/R022).
 *
 * Redacts (recursively, case-insensitive) any key that looks like a secret or
 * PII: password, token, secret, signature, phone, email, owner/customer name,
 * address, NIK, card, raw payment payload, or a raw device identifier. It also
 * caps string length and depth so a hostile payload can never bloat the trace.
 * Mirrors the Sprint 30/31/32/33 redactors so the whole chain redacts alike.
 */
class AndroidSyncRedactor
{
    private const REDACTED = '[REDACTED]';

    /** Substrings that force full redaction of a value when found in a key. */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'passwd', 'token', 'secret', 'signature', 'sign',
        'phone', 'msisdn', 'email', 'owner_name', 'customer', 'name',
        'address', 'nik', 'ktp', 'card', 'pan', 'cvv', 'credential',
        'authorization', 'auth', 'api_key', 'apikey', 'server_key',
        'client_key', 'private_key', 'body', 'payload', 'fingerprint',
        'device_uuid', 'raw',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function redact(array $metadata, int $depth = 0): array
    {
        if ($depth > 6) {
            return ['_truncated' => true];
        }

        $clean = [];

        foreach ($metadata as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if ($this->isSensitiveKey($keyString)) {
                $clean[$keyString] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $clean[$keyString] = $this->redact($value, $depth + 1);

                continue;
            }

            $clean[$keyString] = $this->scalar($value);
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function scalar(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            if (mb_strlen($trimmed) > 200) {
                return mb_substr($trimmed, 0, 200).'…';
            }

            return $trimmed;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return self::REDACTED;
    }
}
