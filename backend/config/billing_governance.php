<?php

/**
 * Sprint 30 — Billing Invoice Generation & Payment Collection Governance Foundation.
 *
 * Canonical, server-side source of truth for tenant billing: the plan pricing a
 * tenant invoice is generated from, the deterministic billing period policy, the
 * invoice status / payment collection state vocabularies, and the foundation
 * rules (BIL-R001..R016) that keep the billing surface safe.
 *
 * Design contract:
 *  - Billing periods are resolved by BillingPeriodService only (BIL-R001).
 *  - Invoice generation is idempotent per tenant + billing period (BIL-R002).
 *  - Invoice amounts come from the tenant's ACTIVE plan pricing here — never from
 *    client input, never ad-hoc in a controller (BIL-R003).
 *  - No-plan / no-price never silently generates a zero invoice; the generator
 *    refuses with a governance error unless the plan is explicitly free (BIL-R003).
 *  - Invoice status and payment collection state use controlled lifecycle
 *    services, not free strings (BIL-R004).
 *  - Payment/invoice mutations are platform-admin only and audit-logged with
 *    redacted metadata (BIL-R006/R007/R008).
 *  - This file contains NO secrets, NO gateway credentials, and NO real customer
 *    data. It is safe to commit and to grep in CI.
 *
 * See docs/architecture/billing-invoice-payment-collection-governance.md and
 * docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */
return [
    // Default billing currency when a plan price does not declare its own.
    'default_currency' => 'IDR',

    // Deterministic billing period policy (BIL-R001). Monthly periods keyed
    // `YYYY-MM`; the due date is period_start + `due_days`. `timezone` fixes the
    // civil day boundaries so a period is stable regardless of server locale.
    'period' => [
        'interval' => 'monthly',
        'due_days' => 7,
        'timezone' => 'Asia/Jakarta',
    ],

    // Plan pricing source of truth (BIL-R003). Keyed by the Sprint 26 tenant plan
    // key. `amount` is whole-rupiah (integer, matching subscription_plans.
    // price_monthly). A `free: true` plan legitimately produces a zero-total
    // invoice; every other plan MUST declare a positive amount or invoice
    // generation refuses (no silent zero). Enterprise/custom amounts are explicit.
    'pricing' => [
        'starter' => ['amount' => 99000, 'currency' => 'IDR', 'interval' => 'monthly', 'active' => true, 'free' => false],
        'growth' => ['amount' => 299000, 'currency' => 'IDR', 'interval' => 'monthly', 'active' => true, 'free' => false],
        'professional' => ['amount' => 799000, 'currency' => 'IDR', 'interval' => 'monthly', 'active' => true, 'free' => false],
        'enterprise' => ['amount' => 2500000, 'currency' => 'IDR', 'interval' => 'monthly', 'active' => true, 'free' => false],
    ],

    // Invoice status lifecycle vocabulary (BIL-R004). `status` describes the
    // document; `collection_state` describes payment. They are separate axes.
    'invoice_statuses' => ['draft', 'issued', 'void', 'cancelled'],
    'collection_states' => ['not_due', 'pending', 'paid', 'failed', 'overdue', 'written_off', 'cancelled'],

    // Payment record status vocabulary (BIL-R004/R009).
    'payment_statuses' => ['pending', 'recorded', 'confirmed', 'failed', 'cancelled', 'refunded'],

    // Allowlisted manual payment methods (validation allowlist). Foundation only —
    // no gateway is called; a "manual"/"bank_transfer" payment is a recorded fact.
    'payment_methods' => ['manual', 'bank_transfer', 'cash', 'qris', 'other'],

    // Allowlisted invoice/payment sources (validation allowlist).
    'sources' => ['platform_admin', 'system', 'cli', 'renewal', 'test'],

    // Partial payment policy (BIL-R009). When false, a recorded payment must
    // settle the full outstanding amount; a smaller amount is rejected rather than
    // silently leaving the invoice partially paid.
    'allow_partial_payments' => false,

    // Overpayment policy (BIL-R009). When false, a payment amount may never exceed
    // the invoice outstanding amount.
    'allow_overpayment' => false,

    // Hard Sprint 30 guardrails. Every flag MUST stay false; a true value forces
    // the readiness / go-no-go decision to NO_GO.
    'invoice_amount_from_client_allowed' => false,
    'invoice_without_plan_pricing_allowed' => false,
    'duplicate_invoice_per_period_allowed' => false,
    'failed_payment_marks_invoice_paid_allowed' => false,
    'paid_invoice_lifts_manual_suspension_allowed' => false,
    'renewal_bypasses_invoice_service_allowed' => false,
    'plan_price_change_mutates_issued_invoice_allowed' => false,
    'tenant_route_can_mutate_invoice_state_allowed' => false,

    // Canonical foundation rules registry. Locked by tests/gates (BIL-R015/R016).
    'rules' => [
        'BIL-R001' => 'Billing periods must be resolved by a canonical server-side billing period service.',
        'BIL-R002' => 'Tenant invoice generation must be idempotent per tenant and billing period.',
        'BIL-R003' => 'Tenant invoices must be generated from the tenant active plan pricing source of truth.',
        'BIL-R004' => 'Invoice status and payment collection state must use controlled lifecycle services, not ad-hoc controller strings.',
        'BIL-R005' => 'Duplicate active invoices for the same tenant and billing period are forbidden.',
        'BIL-R006' => 'Invoice and payment metadata must be redacted and must not store secrets, tokens, credentials, or excessive PII.',
        'BIL-R007' => 'Billing/payment mutations must be platform-admin only unless explicitly governed otherwise.',
        'BIL-R008' => 'Billing/payment mutations must be audit-logged with reason/actor where applicable.',
        'BIL-R009' => 'Payment records must be idempotent and must not overstate collected revenue.',
        'BIL-R010' => 'Failed or cancelled payments must not mark invoices paid.',
        'BIL-R011' => 'Paid invoices must not automatically lift manual tenant suspension.',
        'BIL-R012' => 'Subscription renewal and dunning services may read billing state but must not bypass invoice/payment lifecycle services.',
        'BIL-R013' => 'Plan price changes must not mutate already issued invoices without a governed adjustment flow.',
        'BIL-R014' => 'Billing invoice generation must not weaken tenant lifecycle, entitlement, usage-limit, usage-ledger, repair, or export-governance gates.',
        'BIL-R015' => 'Sprint 30 GO requires billing:go-no-go green.',
        'BIL-R016' => 'Sprint 30 rules must coexist with Sprint 25 TLS-R001..R010, Sprint 26 TPE-R001..R012, Sprint 27 UEL-R001..R015, Sprint 28 ULR-R001..R016, and Sprint 29 EGC-R001..R015.',
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Sprint 30 documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/billing-invoice-payment-collection-governance.md',
        'docs/sprints/sprint-30-billing-invoice-generation-payment-collection-governance-foundation.md',
    ],

    // The Sprint 30 billing commands (self-contract surfaced in go-no-go).
    'billing_commands' => [
        'billing:period-summary',
        'billing:invoice-generate',
        'billing:invoice-summary',
        'billing:collection-summary',
        'billing:governance-audit',
        'billing:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–29) that must remain
    // registered for the billing gate (prior-sprint gate contract).
    'required_commands' => [
        'subscription-renewal:readiness',
        'subscription-renewal:candidate-summary',
        'subscription-renewal:dunning-summary',
        'subscription-renewal:go-no-go',
        'tenant-lifecycle:readiness',
        'tenant-lifecycle:suspension-summary',
        'tenant-lifecycle:enforcement-audit',
        'tenant-lifecycle:go-no-go',
        'tenant-plan:readiness',
        'tenant-plan:entitlement-summary',
        'tenant-plan:usage-limit-summary',
        'tenant-plan:enforcement-audit',
        'tenant-plan:go-no-go',
        'report-export-metering:summary',
        'report-export-metering:enforcement-audit',
        'report-export-metering:go-no-go',
        'usage-ledger:go-no-go',
        'export-governance:route-scan',
        'export-governance:coverage-summary',
        'export-governance:metering-audit',
        'export-governance:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the billing GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'subscription_renewal_gate' => ['subscription-renewal:readiness', 'subscription-renewal:candidate-summary', 'subscription-renewal:dunning-summary', 'subscription-renewal:go-no-go'],
        'tenant_lifecycle_gate' => ['tenant-lifecycle:readiness', 'tenant-lifecycle:suspension-summary', 'tenant-lifecycle:enforcement-audit', 'tenant-lifecycle:go-no-go'],
        'tenant_plan_gate' => ['tenant-plan:readiness', 'tenant-plan:enforcement-audit', 'tenant-plan:go-no-go'],
        'report_export_metering_gate' => ['report-export-metering:summary', 'report-export-metering:enforcement-audit', 'report-export-metering:go-no-go'],
        'usage_ledger_gate' => ['usage-ledger:go-no-go'],
        'export_governance_gate' => ['export-governance:route-scan', 'export-governance:coverage-summary', 'export-governance:metering-audit', 'export-governance:go-no-go'],
    ],
];
