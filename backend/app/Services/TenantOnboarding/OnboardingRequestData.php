<?php

namespace App\Services\TenantOnboarding;

use Illuminate\Support\Str;

/**
 * Sprint 33 — the validated, normalized onboarding request. Built from an admin
 * FormRequest or a console command. Owner/cashier credentials are never carried
 * here in raw form beyond what the provisioning services need in a single
 * transaction; nothing PII is ever echoed back (ONB-R024).
 */
final class OnboardingRequestData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $planCode,
        public readonly string $tenantName,
        public readonly ?string $tenantCode,
        public readonly string $ownerName,
        public readonly ?string $ownerEmail,
        public readonly ?string $ownerPhone,
        public readonly string $firstBranchName,
        public readonly ?string $firstBranchCode,
        public readonly ?string $firstCashierName,
        public readonly ?string $firstRegisterName,
        public readonly bool $withTrial,
        public readonly bool $withCashier,
        public readonly bool $withRegister,
        public readonly bool $withInvoice,
        public readonly bool $withPaymentIntent,
        public readonly string $onboardingType,
        public readonly ?int $requestedByUserId,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $planCode = strtolower(trim((string) ($data['plan_code'] ?? '')));

        return new self(
            idempotencyKey: trim((string) ($data['idempotency_key'] ?? '')),
            planCode: $planCode,
            tenantName: trim((string) ($data['tenant_name'] ?? '')),
            tenantCode: isset($data['tenant_code']) && $data['tenant_code'] !== null
                ? Str::upper(trim((string) $data['tenant_code']))
                : null,
            ownerName: trim((string) ($data['owner_name'] ?? 'Owner')),
            ownerEmail: isset($data['owner_email']) ? trim((string) $data['owner_email']) : null,
            ownerPhone: isset($data['owner_phone']) ? trim((string) $data['owner_phone']) : null,
            firstBranchName: trim((string) ($data['first_branch_name'] ?? '')),
            firstBranchCode: isset($data['first_branch_code']) && $data['first_branch_code'] !== null
                ? Str::upper(trim((string) $data['first_branch_code']))
                : null,
            firstCashierName: isset($data['first_cashier_name']) ? trim((string) $data['first_cashier_name']) : null,
            firstRegisterName: isset($data['first_register_name']) ? trim((string) $data['first_register_name']) : null,
            withTrial: (bool) ($data['with_trial'] ?? true),
            withCashier: (bool) ($data['with_cashier'] ?? (bool) config('onboarding_governance.provisioning.first_cashier_required', true)),
            withRegister: (bool) ($data['with_register'] ?? (bool) config('onboarding_governance.provisioning.device_register_setup_required', true)),
            withInvoice: (bool) ($data['with_invoice'] ?? false),
            withPaymentIntent: (bool) ($data['with_payment_intent'] ?? false),
            onboardingType: (string) ($data['onboarding_type'] ?? 'platform_admin'),
            requestedByUserId: isset($data['requested_by_user_id']) ? (int) $data['requested_by_user_id'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }
}
