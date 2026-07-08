<?php

namespace App\Services\UsageEventLedger;

/**
 * Sprint 27 — shared secret/PII redaction for usage event ledger metadata
 * (UEL-R003).
 *
 * Usage events carry small, non-sensitive context (report type, format, route
 * name, sanitized filter summary, actor id/type). No usage event metadata may
 * ever leak a credential, payment gateway secret, token, password, card/CVV, or
 * raw PII. This trait strips `key: value` secret patterns from strings and
 * redacts secret-looking keys from metadata arrays.
 */
trait SanitizesUsageEventMetadata
{
    /** Key fragments (case-insensitive) whose values are redacted. */
    private const REDACTED_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'private_key',
        'server_key', 'client_secret', 'authorization', 'credential', 'app_key',
        'midtrans', 'xendit', 'duitku', 'gateway_key', 'signature', 'cvv',
        'card_number', 'account_password', 'pin', 'otp', 'ssn', 'nik',
    ];

    private const REDACTION = '[REDACTED]';

    protected function sanitizeString(string $value): string
    {
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            $value = (string) preg_replace(
                '/('.preg_quote($fragment, '/').'\s*[:=]\s*)\S+/i',
                '$1'.self::REDACTION,
                $value,
            );
        }

        return $value;
    }

    protected function sanitizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->sanitizeString((string) $value);
    }

    /**
     * @param array<int|string,mixed>|null $data
     * @return array<int|string,mixed>|null
     */
    protected function sanitizeMetadata(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                $clean[$key] = self::REDACTION;

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeMetadata($value);
            } elseif (is_string($value)) {
                $clean[$key] = $this->sanitizeString($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isSecretKey(string $key): bool
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
