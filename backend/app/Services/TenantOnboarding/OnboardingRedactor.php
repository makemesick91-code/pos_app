<?php

namespace App\Services\TenantOnboarding;

/**
 * Sprint 33 — redacts any metadata before it is persisted to the provisioning
 * trace, returned by an API/command, or written to a log (ONB-R023/R024).
 *
 * Redacts (recursively, case-insensitive) any key that looks like a secret or
 * PII: password, token, secret, signature, phone, email, owner_name, customer
 * name, address, NIK, card, or a raw request body. It also caps string lengths
 * and depth so a hostile payload can never bloat the trace.
 */
class OnboardingRedactor
{
    private const REDACTED = '[REDACTED]';

    /** Substrings that force full redaction of a value when found in a key. */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'passwd', 'token', 'secret', 'signature', 'sign',
        'phone', 'msisdn', 'email', 'owner_name', 'customer', 'name',
        'address', 'nik', 'ktp', 'card', 'pan', 'cvv', 'credential',
        'authorization', 'auth', 'api_key', 'apikey', 'body', 'payload',
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

            if ($trimmed === '') {
                return $trimmed;
            }

            return mb_substr($trimmed, 0, 200);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return self::REDACTED;
    }
}
