<?php

namespace App\Services\SupportOperations;

/**
 * Sprint 35 — redacts any metadata before it is persisted to a support incident,
 * note, action ledger row or session, returned by a support API/command, or
 * printed to a support console (SUP-R006/R007/R023).
 *
 * Redacts (recursively, case-insensitive) any key that looks like a secret or
 * PII: password, token, secret, signature, phone, email, owner/customer/user
 * name, address, NIK, card, raw payment/sync payload, or a raw device
 * identifier. It also caps string length and depth so a hostile payload can never
 * bloat the trace. Mirrors the Sprint 30/31/32/33/34 redactors so the whole chain
 * redacts alike.
 */
class SupportRedactor
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

    /**
     * Redact a free-text string (incident title/summary/note body). Strips any
     * inline token/secret-looking run and caps length so PII can never bloat the
     * store. The support layer keeps only safe, short, operator-authored text.
     */
    public function redactText(?string $text, int $maxLength = 2000): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = trim($text);

        // Redact obvious secret-looking runs: long base64/hex tokens, bearer
        // tokens, sk_live_/server keys, and email addresses.
        $patterns = [
            '/\b[A-Za-z0-9._-]*(?:sk_live|sk_test|server_key|client_key|bearer)[A-Za-z0-9._:-]*/i',
            '/[A-Za-z0-9+\/]{40,}={0,2}/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
        ];
        foreach ($patterns as $pattern) {
            $clean = (string) preg_replace($pattern, self::REDACTED, $clean);
        }

        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength).'…';
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
