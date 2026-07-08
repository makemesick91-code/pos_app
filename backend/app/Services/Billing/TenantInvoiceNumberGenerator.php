<?php

namespace App\Services\Billing;

use App\Models\Tenant;

/**
 * Sprint 30 — deterministic, unique invoice number generator.
 *
 * `INV-{YYYYMM}-{tenantId zero-padded}` is unique per tenant + period and stable
 * across retries, which reinforces idempotent generation (BIL-R002). This is a
 * governance identifier, not a legal/tax invoice number — that is deliberately
 * out of scope for the foundation.
 */
class TenantInvoiceNumberGenerator
{
    public function generate(Tenant $tenant, string $periodKey): string
    {
        $compactPeriod = str_replace('-', '', $periodKey);

        return sprintf('INV-%s-%06d', $compactPeriod, $tenant->id);
    }
}
