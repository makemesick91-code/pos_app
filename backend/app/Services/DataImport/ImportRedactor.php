<?php

namespace App\Services\DataImport;

class ImportRedactor
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE = [
        'password', 'passwd', 'token', 'secret', 'signature', 'sign', 'phone', 'msisdn',
        'email', 'owner_name', 'customer_name', 'supplier_name', 'user_name', 'address', 'nik',
        'ktp', 'card', 'pan', 'cvv', 'credential', 'authorization', 'auth', 'api_key',
        'apikey', 'server_key', 'client_key', 'private_key', 'body', 'payload',
        'fingerprint', 'raw', 'path', 'file',
    ];

    public function redact(array $metadata, int $depth = 0): array
    {
        if ($depth > 6) {
            return ['_truncated' => true];
        }

        $clean = [];
        foreach ($metadata as $key => $value) {
            $key = is_string($key) ? $key : (string) $key;
            if ($this->isSensitiveKey($key)) {
                $clean[$key] = self::REDACTED;
                continue;
            }
            $clean[$key] = is_array($value) ? $this->redact($value, $depth + 1) : $this->safeScalar($value);
        }

        return $clean;
    }

    public function redactText(?string $text, int $maxLength = 500): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = trim($text);
        foreach ([
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
            '/\b[A-Za-z0-9._-]*(?:sk_live|sk_test|server_key|client_key|bearer|token|secret)[A-Za-z0-9._:-]*/i',
            '/[A-Za-z0-9+\/]{40,}={0,2}/',
            '#(/[A-Za-z0-9._-]+){3,}#',
        ] as $pattern) {
            $clean = (string) preg_replace($pattern, self::REDACTED, $clean);
        }

        return mb_strlen($clean) > $maxLength ? mb_substr($clean, 0, $maxLength).'...' : $clean;
    }

    public function safeRow(array $row): array
    {
        return $this->redact($row);
    }

    public function containsForbiddenSecretKey(array $row): bool
    {
        foreach ($row as $key => $value) {
            $lower = strtolower((string) $key);
            foreach (['secret', 'token', 'api_key', 'apikey', 'credential', 'server_key', 'client_key', 'private_key', 'password'] as $needle) {
                if (str_contains($lower, $needle)) {
                    return true;
                }
            }
            if (is_array($value) && $this->containsForbiddenSecretKey($value)) {
                return true;
            }
        }

        return false;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function safeScalar(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
            return mb_strlen($value) > 200 ? mb_substr($value, 0, 200).'...' : $value;
        }

        return is_scalar($value) || $value === null ? $value : self::REDACTED;
    }
}
