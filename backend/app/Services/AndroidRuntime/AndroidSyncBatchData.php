<?php

namespace App\Services\AndroidRuntime;

/**
 * Sprint 34 — a validated, safe representation of an inbound Android sync batch.
 * Each item MUST carry a client_item_id (client UUID/idempotency key) — the server
 * rejects an item without one (ADR-R012/R013).
 */
final class AndroidSyncBatchData
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly string $clientBatchId,
        public readonly string $idempotencyKey,
        public readonly ?int $registerId,
        public readonly array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientBatchId: (string) ($data['client_batch_id'] ?? ''),
            idempotencyKey: (string) ($data['idempotency_key'] ?? ($data['client_batch_id'] ?? '')),
            registerId: isset($data['register_id']) ? (int) $data['register_id'] : null,
            items: array_values((array) ($data['items'] ?? [])),
        );
    }
}
