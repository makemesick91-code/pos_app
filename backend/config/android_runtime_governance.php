<?php

/**
 * Sprint 34 — Android Offline, Sync, Device Activation & Cashier Runtime Hardening.
 *
 * Canonical, server-side source of truth for how the Android POS runtime is
 * governed for real UMKM field use: device/register activation, cashier runtime
 * sessions, bounded offline queues, and deterministic, idempotent sync.
 *
 * Design contract (never weakened by a later sprint):
 *  - Every Android runtime write resolves tenant/branch/register/device/cashier
 *    through the canonical App\Services\AndroidRuntime\AndroidRuntimeAccessService
 *    which delegates the billing/lifecycle dimension to the Sprint 32
 *    EntitlementAccessService. No controller/command re-implements the gate
 *    (ADR-R001, ADR-R006).
 *  - Device activation flows only through DeviceActivationService. The activation
 *    token is hashed (sha256) and non-reversible; the raw token is never stored,
 *    never logged, and never returned after creation (ADR-R002, ADR-R003, R020).
 *  - Activation is idempotent per tenant + device fingerprint and respects the
 *    Sprint 32 device/register limit; it fails CLOSED for an unknown tenant/plan
 *    (ADR-R004, ADR-R005, ADR-R006).
 *  - Manual suspension (Sprint 25) always wins; a paid invoice never lifts it and
 *    Android can never mark an invoice paid or unlock entitlement locally
 *    (ADR-R007, ADR-R023, ADR-R024). Unpaid-past-grace and trial-expired fail
 *    closed to blocked/read-only per this config (ADR-R008, ADR-R009).
 *  - Offline sales/orders carry a client UUID / idempotency key; the server
 *    rejects a duplicate client UUID without a duplicate mutation and a replayed
 *    batch is idempotent (ADR-R012, ADR-R013, ADR-R014, ADR-R015). Sync leans on
 *    the Sprint 7 SaleService client_reference idempotency — it never bypasses the
 *    POS domain service.
 *  - Conflict decisions are deterministic and explainable via stable codes
 *    (ADR-R016). Catalog/stock/price snapshots are tenant-isolated (ADR-R017/R018).
 *  - This file holds NO secrets and NO real tenant/customer data; it is safe to
 *    commit and grep in CI. No runtime output (audit, command, smoke, docs, API,
 *    Android log) may leak secrets or PII (ADR-R020, R021, R022).
 *
 * See docs/architecture/sprint-34-android-offline-sync-device-activation-cashier-runtime-hardening.md
 * and docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */

return [
    // ADR-R002 — governed device activation is enabled by default.
    'device_activation_enabled' => env('ANDROID_DEVICE_ACTIVATION_ENABLED', true),

    // ADR-R003 — activation token hash/fingerprint policy. The raw token is never
    // persisted; only a sha256 hash is stored, and it is never returned after the
    // one-time prepare step.
    'activation_token' => [
        'hash_algo' => 'sha256',
        'fingerprint_hash_algo' => 'sha256',
        'min_token_length' => 8,
        'max_token_length' => 128,
        // Whether activate() may auto-prepare a pending activation when no prepared
        // token row exists yet (used by the deterministic simulate command/tests and
        // by the governed admin/onboarding hand-off). It NEVER weakens the entitlement
        // gate — an auto-prepared activation still fails closed for suspended/over-limit.
        'allow_auto_prepare' => true,
        'return_raw_token_after_creation' => false,
        'store_raw_token' => false,
    ],

    // ADR-R003 — activation token expiry policy.
    'activation_token_ttl_minutes' => env('ANDROID_ACTIVATION_TTL_MINUTES', 1440),

    // ADR-R004/R006 — max failed activation attempts before the pending activation
    // is failed-closed and must be re-prepared.
    'max_activation_attempts' => env('ANDROID_MAX_ACTIVATION_ATTEMPTS', 5),

    // ADR-R012/R019 — offline queue policy exposed to Android.
    'offline' => [
        'mode_allowed' => env('ANDROID_OFFLINE_MODE_ALLOWED', true),
        // ADR-R019 — bounded queue size and age.
        'queue_max_items' => env('ANDROID_OFFLINE_QUEUE_MAX_ITEMS', 500),
        'queue_max_age_hours' => env('ANDROID_OFFLINE_QUEUE_MAX_AGE_HOURS', 72),
        // ADR-R012 — offline sales/orders must carry a client UUID/idempotency key.
        'require_client_uuid' => true,
        // Actions Android may enqueue while offline. Read-only snapshots are always
        // allowed; payment settlement is NEVER an offline-decidable action.
        'allowed_actions' => ['sale', 'order', 'customer_snapshot', 'inventory_snapshot'],
    ],

    // ADR-R014 — sync batch idempotency requirement.
    'sync' => [
        'batch_idempotency_required' => true,
        'max_items_per_batch' => env('ANDROID_SYNC_MAX_ITEMS_PER_BATCH', 200),
        'require_item_client_id' => true,
        // Item types the ingestion service understands.
        'item_types' => ['sale', 'order', 'payment', 'customer_snapshot', 'inventory_snapshot', 'other'],
        'item_actions' => ['create', 'update', 'void', 'sync_snapshot'],
    ],

    // ADR-R016 — deterministic conflict handling policy. Each code maps to a safe,
    // explainable message. Never leaks PII.
    'conflict_policy' => 'server_authoritative_deterministic',
    'conflict_codes' => [
        'duplicate_client_item' => 'A record with this client item id was already accepted; no duplicate created.',
        'stale_catalog_version' => 'The catalog version the client used is stale; refresh required.',
        'stale_price_snapshot'  => 'The price snapshot the client used is stale; refresh required.',
        'register_mismatch'     => 'The register/device on the item does not match the activated device.',
        'device_revoked'        => 'The device activation has been revoked; sync/write is blocked.',
        'tenant_read_only'      => 'The tenant is read-only; writes are blocked.',
        'tenant_suspended'      => 'The tenant is manually suspended; writes are blocked.',
        'unpaid_past_grace'     => 'The tenant is unpaid past grace; writes are blocked/read-only.',
        'trial_expired'         => 'The tenant trial has expired; writes are blocked/read-only.',
        'entitlement_denied'    => 'The action is denied by the plan entitlement gate.',
        'invalid_payload'       => 'The item payload is invalid or incomplete.',
    ],

    // ADR-R010 — cashier session policy.
    'cashier' => [
        'session_timeout_minutes' => env('ANDROID_CASHIER_SESSION_TIMEOUT_MINUTES', 720),
        // Roles allowed to operate a cashier runtime session on Android. These are
        // the real tenant user roles (App\Models\User::ROLE_*); saas_admin carries
        // no tenant and is intentionally excluded.
        'operator_roles' => ['tenant_owner', 'store_admin', 'cashier'],
    ],

    // ADR-R007/R008/R009 — suspended / unpaid-past-grace / trial-expired behaviour
    // on Android. `block` forbids writes entirely (fail closed); `read_only` allows
    // reads/snapshots but no mutations. Never `allow`.
    'runtime_behavior' => [
        'suspended' => 'block',
        'unpaid_past_grace' => 'block',
        'trial_expired' => 'read_only',
        // ADR-R025 — when the entitlement snapshot the client holds is stale, the
        // client must fail safe (treat as read-only) rather than assume access.
        'stale_entitlement' => 'read_only',
    ],

    // ADR-R020/R021/R022 — no secret/PII output anywhere.
    'redaction' => [
        'required' => true,
        'redact_metadata' => true,
    ],

    // Activation lifecycle statuses (tenant_device_activations.activation_status).
    'activation_statuses' => ['pending', 'activated', 'revoked', 'expired', 'failed'],

    // Sync batch lifecycle statuses (tenant_android_sync_batches.status).
    'sync_batch_statuses' => ['received', 'processing', 'completed', 'partial_failed', 'failed', 'replayed', 'rejected'],

    // Sync item result statuses (tenant_android_sync_items.status).
    'sync_item_statuses' => ['accepted', 'rejected', 'duplicate', 'conflict', 'failed', 'skipped'],

    // Hard Sprint 34 guardrails. Every flag MUST stay false; a true value forces
    // the go-no-go decision to NO_GO.
    'raw_activation_token_returned_after_creation_allowed' => false,
    'raw_activation_token_stored_allowed' => false,
    'android_marks_invoice_paid_allowed' => false,
    'android_unlocks_entitlement_locally_allowed' => false,
    'sync_bypasses_pos_domain_service_allowed' => false,
    'revoked_device_can_sync_allowed' => false,
    'duplicate_client_uuid_double_mutation_allowed' => false,
    'runtime_bypasses_entitlement_service_allowed' => false,
    'manual_suspension_overridable_by_billing_allowed' => false,
    'raw_credential_in_output_allowed' => false,

    // The Sprint 34 Android runtime commands (self-contract surfaced in go-no-go).
    'android_runtime_commands' => [
        'android-runtime:device-summary',
        'android-runtime:activation-simulate',
        'android-runtime:sync-summary',
        'android-runtime:sync-simulate',
        'android-runtime:cashier-check',
        'android-runtime:governance-audit',
        'android-runtime:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–33) that must remain
    // registered for the Android runtime gate (prior-sprint gate contract, ADR-R029).
    'required_commands' => [
        'subscription-renewal:go-no-go',
        'tenant-lifecycle:go-no-go',
        'tenant-plan:go-no-go',
        'report-export-metering:go-no-go',
        'usage-ledger:go-no-go',
        'export-governance:go-no-go',
        'billing:go-no-go',
        'payment-gateway:go-no-go',
        'entitlement:go-no-go',
        'onboarding:go-no-go',
    ],

    // Required documentation contract (ADR-R030). Paths are repo-root relative.
    'required_docs' => [
        'docs/architecture/sprint-34-android-offline-sync-device-activation-cashier-runtime-hardening.md',
        'docs/sprints/sprint-34-android-runtime-hardening-evidence.md',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (ADR-R020/R030).
    'rules' => [
        'ADR-R001' => 'Android runtime access must resolve tenant/register/device through the canonical backend AndroidRuntimeAccessService.',
        'ADR-R002' => 'Device activation must use DeviceActivationService.',
        'ADR-R003' => 'The activation token must be hashed/non-reversible and never returned after creation.',
        'ADR-R004' => 'Activation must be idempotent per tenant/register/device fingerprint.',
        'ADR-R005' => 'Activation must respect the Sprint 32 device/register limits.',
        'ADR-R006' => 'Activation must fail closed for an unknown tenant/register/plan.',
        'ADR-R007' => 'Manual suspension blocks Android writes regardless of billing/payment state.',
        'ADR-R008' => 'Unpaid past grace blocks Android writes or forces read-only per governance.',
        'ADR-R009' => 'Trial expired blocks Android writes or forces read-only per governance.',
        'ADR-R010' => 'Cashier login/session must validate tenant, branch, register, device, role, and entitlement.',
        'ADR-R011' => 'Cashier runtime decisions must be audit-logged when denied/degraded.',
        'ADR-R012' => 'Offline sales/orders must carry a client UUID/idempotency key.',
        'ADR-R013' => 'The server must reject a duplicate client UUID without a duplicate mutation.',
        'ADR-R014' => 'A sync batch must be idempotent and retry-safe.',
        'ADR-R015' => 'A failed sync item must be retryable without duplicating a sale/order.',
        'ADR-R016' => 'The conflict policy must be deterministic and explainable.',
        'ADR-R017' => 'Catalog/settings sync must be tenant-isolated.',
        'ADR-R018' => 'Stock/price/customer/payment-method snapshots must not leak other tenants.',
        'ADR-R019' => 'The offline queue must have a bounded size and age.',
        'ADR-R020' => 'Android local storage must avoid raw secrets/PII where possible.',
        'ADR-R021' => 'Android logs must not leak tokens/passwords/PII.',
        'ADR-R022' => 'Sync API output must be redacted and safe.',
        'ADR-R023' => 'Payment settlement state must only come from the Sprint 30/31 trusted services.',
        'ADR-R024' => 'Android must not mark an invoice paid or unlock entitlement locally.',
        'ADR-R025' => 'Entitlement state refresh must fail safe when stale.',
        'ADR-R026' => 'Device revoke/disable must block future sync/write.',
        'ADR-R027' => 'A register/device mismatch must be denied and audited.',
        'ADR-R028' => 'A platform-admin/device-support bypass must be explicit and audited.',
        'ADR-R029' => 'Prior Sprint 24–33 gates must remain green.',
        'ADR-R030' => 'Go/no-go must verify activation, offline queue, sync idempotency, cashier runtime, entitlement, and redaction.',
    ],
];
