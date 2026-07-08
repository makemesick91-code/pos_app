<?php

/**
 * Sprint 31 — Payment Gateway / QRIS Settlement Governance Foundation.
 *
 * Provider-neutral governance source of truth for settling Sprint 30 tenant
 * billing invoices (tenant_billing_*) through a payment gateway/QRIS channel.
 * This is a SAFE FOUNDATION: the default provider is the deterministic `mock`
 * provider, no real gateway is called in CI, and NO secret/credential/PII value
 * lives in this file — only env variable NAMES are referenced (never their
 * values) so a real provider can be wired later.
 *
 * Design contract:
 *  - A provider must be explicitly configured; the default is `mock` and CI never
 *    performs a real gateway call (PGW-R001/R002).
 *  - A payment intent is idempotent per invoice + provider + channel (PGW-R003)
 *    and can never be created for an already-paid invoice (PGW-R004).
 *  - The intent amount equals the invoice outstanding amount; partial payment and
 *    overpayment are rejected unless explicitly enabled (PGW-R005/R006).
 *  - Webhooks require a verified signature (PGW-R007) and replay is idempotent
 *    (PGW-R008). A failed/cancelled/expired/rejected event NEVER marks an invoice
 *    paid (PGW-R009).
 *  - Settlement of a verified paid event goes through the Sprint 30 payment
 *    collection service, never a direct invoice mutation (PGW-R010), and never
 *    lifts a manual tenant suspension (PGW-R013).
 *  - Metadata/payloads are redacted; this file, command output, and API responses
 *    never leak secrets/PII (PGW-R011/R016).
 *
 * Kept SEPARATE from the Sprint 5 POS QRIS surface (App\Services\Payments,
 * /webhooks/payments/{provider}) — that settles point-of-sale transactions;
 * this settles SaaS billing invoices. See
 * docs/architecture/sprint-31-payment-gateway-qris-settlement-governance.md.
 */
return [
    // Explicitly configured provider (PGW-R001). Default is the deterministic
    // `mock` provider so tests/smoke never touch a network. A real provider is
    // opt-in via env; the value is a KEY into `providers` below, never a secret.
    'default_provider' => env('PAYMENT_GATEWAY_PROVIDER', 'mock'),

    // Master switch. When false (the CI/default state), only the mock provider is
    // usable and no real gateway call is ever made (PGW-R002).
    'live_gateway_enabled' => (bool) env('PAYMENT_GATEWAY_LIVE_ENABLED', false),

    'default_currency' => 'IDR',

    // Provider registry. `credentials_env` lists env variable NAMES a real
    // provider would read at runtime — this file never stores their values.
    // `mock` is deterministic and requires no credentials (PGW-R018).
    'providers' => [
        'mock' => [
            'label' => 'Deterministic Mock QRIS (tests/smoke)',
            'enabled' => true,
            'live' => false,
            'channels' => ['mock_qris'],
            'credentials_env' => [],
        ],
        // Real providers are declared but DISABLED by default; wiring them is a
        // later sprint. Only env variable names appear here — never values.
        'midtrans' => [
            'label' => 'Midtrans QRIS',
            'enabled' => (bool) env('PAYMENT_GATEWAY_MIDTRANS_ENABLED', false),
            'live' => true,
            'channels' => ['qris'],
            'credentials_env' => ['PAYMENT_GATEWAY_MIDTRANS_SERVER_KEY', 'PAYMENT_GATEWAY_MIDTRANS_CLIENT_KEY'],
        ],
        'xendit' => [
            'label' => 'Xendit QRIS',
            'enabled' => (bool) env('PAYMENT_GATEWAY_XENDIT_ENABLED', false),
            'live' => true,
            'channels' => ['qris'],
            'credentials_env' => ['PAYMENT_GATEWAY_XENDIT_SECRET_KEY', 'PAYMENT_GATEWAY_XENDIT_WEBHOOK_TOKEN'],
        ],
    ],

    // QRIS/settlement channels. A channel is only usable if it is listed on the
    // resolved provider above. `mock_qris` is the deterministic test channel.
    'channels' => ['mock_qris', 'qris'],

    // Payment intent status vocabulary.
    'intent_statuses' => ['pending', 'requires_action', 'paid', 'expired', 'failed', 'cancelled'],

    // Gateway event ingestion status vocabulary.
    'event_statuses' => ['received', 'verified', 'rejected', 'processed', 'ignored', 'replayed'],

    // Normalized provider event → internal settlement outcome mapping. Only
    // `paid`/`settled` may drive a settlement; every other status updates intent/
    // event state but NEVER marks an invoice paid (PGW-R009).
    'event_status_map' => [
        'paid' => 'paid',
        'settled' => 'paid',
        'success' => 'paid',
        'completed' => 'paid',
        'pending' => 'pending',
        'requires_action' => 'requires_action',
        'expired' => 'expired',
        'failed' => 'failed',
        'error' => 'failed',
        'declined' => 'failed',
        'rejected' => 'failed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'voided' => 'cancelled',
    ],

    // The only normalized statuses that are allowed to settle an invoice paid.
    'settleable_statuses' => ['paid'],

    // Partial payment policy (PGW-R005). When false, a settlement amount must
    // equal the intent/outstanding amount exactly.
    'allow_partial_payment' => false,

    // Overpayment policy (PGW-R006). When false, a settlement amount may never
    // exceed the intent/outstanding amount.
    'allow_overpayment' => false,

    // Multiple concurrent active unpaid intents for one invoice/provider/channel
    // are forbidden by default; the intent service is idempotent instead (PGW-R003).
    'allow_multiple_active_intents' => false,

    // Webhook security posture. All MUST stay true in the foundation.
    'webhook_signature_required' => true,
    'replay_protection_required' => true,
    'idempotency_required' => true,
    'raw_payload_redaction_enabled' => true,

    // Intent expiry policy. `intent_ttl_minutes` bounds how long an intent stays
    // payable; a settlement arriving after expiry is refused (never silently paid).
    'intent_ttl_minutes' => 30,

    // Hard Sprint 31 guardrails. Every flag MUST stay false; a true value forces
    // the go/no-go decision to NO_GO.
    'live_gateway_call_in_ci_allowed' => false,
    'unsigned_webhook_allowed' => false,
    'failed_event_marks_invoice_paid_allowed' => false,
    'settlement_bypasses_collection_service_allowed' => false,
    'settlement_lifts_manual_suspension_allowed' => false,
    'tenant_route_can_mutate_gateway_state_allowed' => false,
    'secrets_in_gateway_metadata_allowed' => false,
    'duplicate_provider_reference_settlement_allowed' => false,

    // Canonical foundation rules registry. Locked by tests/gates (PGW-R016).
    'rules' => [
        'PGW-R001' => 'A payment gateway provider must be explicitly configured; the default is a deterministic mock.',
        'PGW-R002' => 'No real payment gateway call may be made in CI by default; the mock provider is used.',
        'PGW-R003' => 'A gateway payment intent must be idempotent per invoice, provider, and channel.',
        'PGW-R004' => 'A paid invoice must not be able to create a new payable payment intent.',
        'PGW-R005' => 'A settlement amount must match the invoice outstanding amount unless partial payment is explicitly enabled.',
        'PGW-R006' => 'Overpayment must be rejected unless explicitly enabled.',
        'PGW-R007' => 'A gateway webhook must carry a verified provider signature.',
        'PGW-R008' => 'Webhook replay must be idempotent; a duplicate provider event must never be reprocessed.',
        'PGW-R009' => 'A failed, cancelled, expired, or rejected event must never mark an invoice paid.',
        'PGW-R010' => 'Settlement must use the Sprint 30 payment collection service, never a direct invoice mutation.',
        'PGW-R011' => 'Settlement/intent/event metadata must be redacted and must not store secrets, signatures, or PII.',
        'PGW-R012' => 'Provider reference uniqueness must be enforced so a single provider payment settles once.',
        'PGW-R013' => 'A manual tenant suspension must never be lifted by a payment settlement.',
        'PGW-R014' => 'All admin gateway mutations must require platform.admin.',
        'PGW-R015' => 'There must be no tenant/public route that can mutate gateway/intent/settlement state (the verified webhook is not a tenant mutation route).',
        'PGW-R016' => 'Gateway audit/command/API output must not leak secrets or PII.',
        'PGW-R017' => 'Gateway go/no-go must verify Sprint 30 billing-layer compatibility.',
        'PGW-R018' => 'The mock provider must be deterministic for tests and smoke.',
    ],

    // Repo-root relative docs that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/sprint-31-payment-gateway-qris-settlement-governance.md',
        'docs/sprints/sprint-31-payment-gateway-qris-settlement-governance-evidence.md',
    ],

    // The Sprint 31 gateway commands (self-contract surfaced in go-no-go).
    'gateway_commands' => [
        'payment-gateway:provider-summary',
        'payment-gateway:intent-create',
        'payment-gateway:webhook-simulate',
        'payment-gateway:event-summary',
        'payment-gateway:settlement-summary',
        'payment-gateway:go-no-go',
    ],

    // Sprint 30 billing-layer commands that MUST remain registered — settlement
    // depends on the Sprint 30 collection layer (PGW-R017).
    'billing_layer_commands' => [
        'billing:period-summary',
        'billing:invoice-generate',
        'billing:invoice-summary',
        'billing:collection-summary',
        'billing:governance-audit',
        'billing:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–30) that must remain
    // registered for the gateway gate (prior-sprint gate contract).
    'prior_sprint_gates' => [
        'billing_gate' => ['billing:governance-audit', 'billing:go-no-go'],
        'subscription_renewal_gate' => ['subscription-renewal:go-no-go'],
        'tenant_lifecycle_gate' => ['tenant-lifecycle:go-no-go'],
        'tenant_plan_gate' => ['tenant-plan:go-no-go'],
        'report_export_metering_gate' => ['report-export-metering:go-no-go'],
        'usage_ledger_gate' => ['usage-ledger:go-no-go'],
        'export_governance_gate' => ['export-governance:go-no-go'],
    ],
];
