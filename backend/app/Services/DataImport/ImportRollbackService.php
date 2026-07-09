<?php

namespace App\Services\DataImport;

use App\Models\InventoryMovement;
use App\Models\TenantDataImportRow;
use App\Models\TenantDataImportRun;
use App\Models\User;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportRollbackService
{
    public function __construct(
        private readonly ImportAuditService $audit,
        private readonly InventoryMovementService $inventory,
    ) {}

    public function rollback(TenantDataImportRun $run, ?User $actor, bool $execute = false, ?string $reasonCode = null): array
    {
        if ($execute) {
            $this->audit->assertReasonCode($reasonCode);
            if ($actor === null) {
                throw ValidationException::withMessages(['actor' => 'A platform admin actor is required for rollback execute.']);
            }
        }

        $createdRows = $run->rows()->where('status', TenantDataImportRow::STATUS_CREATED)->get();
        $summary = ['mode' => $execute ? 'execute' : 'dry_run', 'rollbackable_rows' => $createdRows->count(), 'rolled_back_rows' => 0, 'unsupported_rows' => 0];

        if (! $execute) {
            return $summary;
        }

        DB::transaction(function () use ($createdRows, $run, &$summary) {
            foreach ($createdRows as $row) {
                $subject = $row->subject_type;
                if (! is_string($subject) || ! class_exists($subject)) {
                    $summary['unsupported_rows']++;
                    continue;
                }

                if ($subject === InventoryMovement::class) {
                    $movement = InventoryMovement::query()->where('tenant_id', $run->tenant_id)->whereKey($row->subject_id)->first();
                    if ($movement !== null) {
                        $this->inventory->createAdjustmentOut((int) $movement->tenant_id, (int) $movement->store_id, (int) $movement->product_id, (string) $movement->qty, 'rollback import:'.$run->id);
                    }
                } else {
                    $model = $subject::query()->where('tenant_id', $run->tenant_id)->whereKey($row->subject_id)->first();
                    if ($model === null) {
                        $summary['unsupported_rows']++;
                        continue;
                    }
                    $model->delete();
                }

                $row->update(['status' => TenantDataImportRow::STATUS_ROLLED_BACK, 'processed_at' => now()]);
                $summary['rolled_back_rows']++;
            }

            $run->update(['status' => TenantDataImportRun::STATUS_ROLLED_BACK, 'rolled_back_at' => now()]);
        });

        if ($actor !== null) {
            $this->audit->record($actor, $run, 'rolled_back', $reasonCode, $summary);
        }

        return $summary;
    }
}
