<?php

namespace App\Http\Resources;

use App\Services\DataImport\ImportRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDataImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $redactor = app(ImportRedactor::class);

        return [
            'id' => $this->id,
            'run_id' => $this->tenant_data_import_run_id,
            'row_number' => $this->row_number,
            'row_type' => $this->row_type,
            'status' => $this->status,
            'action' => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'error_code' => $this->error_code,
            'error_message_safe' => $this->error_message_safe,
            'normalized' => $redactor->redact((array) $this->normalized_json),
            'processed_at' => $this->processed_at?->toISOString(),
        ];
    }
}
