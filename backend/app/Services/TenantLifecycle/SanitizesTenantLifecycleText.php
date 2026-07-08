<?php

namespace App\Services\TenantLifecycle;

/**
 * Sprint 25 — sanitizes free-text lifecycle reasons/metadata so a suspension
 * reason can never smuggle a secret, token, or payment credential into the
 * event trail or audit log (TLS-R005, TLS-R006).
 */
trait SanitizesTenantLifecycleText
{
    /**
     * Substrings that must never appear verbatim in a persisted reason. Matched
     * case-insensitively; the offending token is masked, the rest is kept.
     */
    private function secretFragments(): array
    {
        return [
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
            'private_key',
            'server_key',
            'client_key',
            'signature',
            'credential',
            'bearer',
        ];
    }

    protected function sanitizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', $reason) ?? '');

        foreach ($this->secretFragments() as $fragment) {
            // Mask "<fragment>: value" and "<fragment>=value" style disclosures.
            $clean = (string) preg_replace(
                '/\b'.preg_quote($fragment, '/').'\b\s*[:=]\s*\S+/i',
                '[REDACTED]',
                $clean,
            );
        }

        return $clean === '' ? null : mb_substr($clean, 0, 1000);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    protected function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $out = [];
        foreach ($metadata as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : '';
            $isSecretKey = false;
            foreach ($this->secretFragments() as $fragment) {
                if (str_contains($lowerKey, $fragment)) {
                    $isSecretKey = true;
                    break;
                }
            }

            if ($isSecretKey) {
                continue;
            }

            // Only keep scalar leaves; drop nested structures that could hide payloads.
            if (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? $this->sanitizeReason($value) : $value;
            }
        }

        return $out === [] ? null : $out;
    }
}
