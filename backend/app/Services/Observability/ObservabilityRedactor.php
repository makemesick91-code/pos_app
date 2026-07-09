<?php

namespace App\Services\Observability;

/**
 * Sprint 36 — redacts any metadata/text before it is persisted to an
 * observability snapshot/anomaly/scheduler-run/alert-suggestion row, returned by
 * an observability API/command, printed to a health endpoint, or written to an
 * audit log (OBS-R004/R009).
 *
 * Recursively (case-insensitive) redacts any key that looks like a secret or PII:
 * password, token, secret, signature, api key, phone, email, owner/customer/user
 * name, address, NIK, card, raw payment/webhook/sync payload, DB connection
 * string, or a storage path with a tenant-identifying component. Caps string
 * length + depth so a hostile payload can never bloat the trace. Mirrors the
 * Sprint 30–35 redactors so the whole chain redacts alike.
 */
class ObservabilityRedactor
{
    private const REDACTED = '[REDACTED]';

    /** Substrings that force full redaction of a value when found in a key. */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'passwd', 'token', 'secret', 'signature', 'sign',
        'phone', 'msisdn', 'email', 'owner_name', 'customer', 'name',
        'address', 'nik', 'ktp', 'card', 'pan', 'cvv', 'credential',
        'authorization', 'auth', 'api_key', 'apikey', 'server_key',
        'client_key', 'private_key', 'body', 'payload', 'fingerprint',
        'device_uuid', 'raw', 'exception', 'stack', 'trace', 'dsn',
        'connection_string', 'password_hash', 'path',
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
     * Redact a free-text string (a failure reason, exception message, summary).
     * Strips inline secret-looking runs, email addresses, and absolute paths, and
     * caps length so PII can never bloat the store.
     */
    public function redactText(?string $text, int $maxLength = 500): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = trim($text);

        $patterns = [
            '/\b[A-Za-z0-9._-]*(?:sk_live|sk_test|server_key|client_key|bearer)[A-Za-z0-9._:-]*/i',
            '/[A-Za-z0-9+\/]{40,}={0,2}/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
            // Absolute filesystem paths that could carry a tenant-identifying dir.
            '#(/[A-Za-z0-9._-]+){3,}#',
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
