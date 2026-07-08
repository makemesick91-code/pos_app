<?php

namespace App\Services\UsageEventLedger;

use App\Models\TenantUsageEvent;

/**
 * Sprint 27 — the immutable result of an attempt to append a usage event.
 *
 * Produced only by UsageEventRecorder. `recorded` is true when a NEW event was
 * persisted; `duplicate` is true when an idempotent duplicate was detected and no
 * new usage was counted (UEL-R004). Never carries secrets.
 */
final class UsageEventDecision
{
    public function __construct(
        public readonly bool $recorded,
        public readonly bool $duplicate,
        public readonly string $eventKey,
        public readonly ?string $meterKey,
        public readonly string $periodKey,
        public readonly ?TenantUsageEvent $event = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'recorded' => $this->recorded,
            'duplicate' => $this->duplicate,
            'event_key' => $this->eventKey,
            'meter_key' => $this->meterKey,
            'period_key' => $this->periodKey,
            'event_id' => $this->event?->id,
        ];
    }
}
