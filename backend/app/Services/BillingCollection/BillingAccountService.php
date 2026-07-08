<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingAccount;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing account lifecycle.
 *
 * Creates/updates a billing account and may link it to an existing tenant, but
 * NEVER creates a tenant and a status change NEVER suspends tenant access. A
 * billing account is platform-to-tenant billing governance, never a POS
 * cashier/customer payment. Secret-looking free-text/metadata is stripped.
 */
class BillingAccountService
{
    use SanitizesBillingCollectionText;

    public const DECISION_GO = 'GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SaasBillingAccount
    {
        return SaasBillingAccount::query()->create([
            'account_reference' => (string) ($attributes['account_reference'] ?? $this->generateReference()),
            // Link an EXISTING tenant only — never provisioned here.
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'billing_name' => $this->sanitizeString((string) ($attributes['billing_name'] ?? 'Unnamed account')),
            'billing_email' => $this->sanitizeNullableString($attributes['billing_email'] ?? null),
            'billing_phone' => $this->sanitizeNullableString($attributes['billing_phone'] ?? null),
            'billing_address' => $this->sanitizeNullableString($attributes['billing_address'] ?? null),
            'tax_identifier' => $this->sanitizeNullableString($attributes['tax_identifier'] ?? null),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SaasBillingAccount::STATUS_ACTIVE)),
            'billing_currency' => strtoupper((string) ($attributes['billing_currency'] ?? config('billing_collection.currency', 'IDR'))),
            'payment_terms_days' => isset($attributes['payment_terms_days']) ? max(0, (int) $attributes['payment_terms_days']) : 7,
            'collection_owner_user_id' => $attributes['collection_owner_user_id'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(SaasBillingAccount $account, array $attributes, ?User $actor = null): SaasBillingAccount
    {
        $map = [
            'tenant_id' => fn ($v) => $v,
            'billing_name' => fn ($v) => $this->sanitizeString((string) $v),
            'billing_email' => fn ($v) => $this->sanitizeNullableString($v),
            'billing_phone' => fn ($v) => $this->sanitizeNullableString($v),
            'billing_address' => fn ($v) => $this->sanitizeNullableString($v),
            'tax_identifier' => fn ($v) => $this->sanitizeNullableString($v),
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'billing_currency' => fn ($v) => strtoupper((string) $v),
            'payment_terms_days' => fn ($v) => max(0, (int) $v),
            'collection_owner_user_id' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $account->{$key} = $caster($attributes[$key]);
            }
        }

        $account->save();

        return $account->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $all = SaasBillingAccount::query()->get();

        $byStatus = [];
        foreach (SaasBillingAccount::STATUSES as $status) {
            $count = $all->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status] = $count;
            }
        }

        return [
            'decision' => self::DECISION_GO,
            'total_accounts' => $all->count(),
            'by_status' => $byStatus,
            'linked_to_tenant' => $all->whereNotNull('tenant_id')->count(),
            'auto_tenant_creation' => false,
            'auto_tenant_suspension' => false,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SaasBillingAccount::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid billing account status: {$status}");
        }

        return $status;
    }

    private function generateReference(): string
    {
        return 'BILL-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
