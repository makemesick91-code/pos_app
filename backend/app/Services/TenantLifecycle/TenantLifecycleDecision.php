<?php

namespace App\Services\TenantLifecycle;

/**
 * Sprint 25 — the immutable result of a tenant lifecycle resolution.
 *
 * Produced only by TenantLifecycleService. Carries the authoritative lifecycle
 * status, whether operational access is allowed/blocked, the stable machine
 * code and human reason for a block, and the source that decided it (manual
 * suspension has precedence over tenant status / subscription — TLS-R004).
 * Never carries secrets.
 */
final class TenantLifecycleDecision
{
    public const SOURCE_MANUAL_SUSPENSION = 'manual_suspension';
    public const SOURCE_TENANT_STATUS = 'tenant_status';
    public const SOURCE_SUBSCRIPTION = 'subscription';
    public const SOURCE_DEFAULT = 'default';

    public function __construct(
        public readonly string $status,
        public readonly bool $allowed,
        public readonly ?string $code,
        public readonly ?string $reason,
        public readonly string $source,
        public readonly bool $manuallySuspended,
        public readonly ?int $manualSuspensionId = null,
    ) {}

    public function blocked(): bool
    {
        return ! $this->allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_status' => $this->status,
            'allowed' => $this->allowed,
            'blocked' => $this->blocked(),
            'code' => $this->code,
            'reason' => $this->reason,
            'source' => $this->source,
            'manually_suspended' => $this->manuallySuspended,
            'manual_suspension_id' => $this->manualSuspensionId,
        ];
    }
}
