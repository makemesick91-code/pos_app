<?php

namespace App\Services\PaymentGateway;

use App\Services\Billing\BillingMetadataSanitizer;

/**
 * Sprint 31 — safe redaction for the gateway surface (PGW-R011/R016). Reuses the
 * Sprint 30 BillingMetadataSanitizer (drops secret/token/signature/card/PII-like
 * keys, truncates long strings) and adds gateway-specific helpers: a truncated,
 * non-reversible signature fingerprint (never the raw signature) and a canonical
 * payload hash for replay detection.
 */
class PaymentGatewayRedactor
{
    public function __construct(
        private readonly BillingMetadataSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $metadata): ?array
    {
        return $this->sanitizer->sanitize($metadata);
    }

    /**
     * A stable, non-reversible fingerprint of a signature — safe to store/log. The
     * raw signature is never persisted (PGW-R011).
     */
    public function signatureHash(?string $signature): ?string
    {
        if ($signature === null || $signature === '') {
            return null;
        }

        return substr(hash('sha256', $signature), 0, 32);
    }

    /**
     * A canonical hash of a payload for replay detection (PGW-R008). Keys are
     * sorted so semantically identical payloads hash identically.
     *
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $payload): string
    {
        $canonical = $this->canonicalize($payload);

        return hash('sha256', (string) json_encode($canonical));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalize(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->canonicalize($value);
            }
        }

        return $payload;
    }
}
