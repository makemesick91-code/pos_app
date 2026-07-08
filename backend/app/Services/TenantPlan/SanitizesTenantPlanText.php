<?php

namespace App\Services\TenantPlan;

/**
 * Sprint 26 — sanitizes free-text plan assignment / entitlement override reasons
 * and metadata so a reason can never smuggle a secret, token, or payment
 * credential into the audit log or governance trail (TPE-R007).
 */
trait SanitizesTenantPlanText
{
    /**
     * Substrings that must never appear verbatim in a persisted reason.
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

            if (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? $this->sanitizeReason($value) : $value;
            }
        }

        return $out === [] ? null : $out;
    }
}
