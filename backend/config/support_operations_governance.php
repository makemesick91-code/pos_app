<?php

/**
 * Sprint 35 — Support Console, Tenant Operations & Incident Diagnostics.
 *
 * Canonical, server-side source of truth for how the platform support console
 * operates so real tenants can be supported WITHOUT opening the database directly.
 *
 * Design contract (never weakened by a later sprint):
 *  - The support console is platform.admin only (SUP-R001). No tenant/public route
 *    may gain any support mutation power (SUP-R027).
 *  - The console is READ-ONLY by default (SUP-R004). Every mutation (device
 *    revoke/reactivate, incident create/update, note add, read-only context
 *    start/end) requires an explicit reason code (SUP-R005) and is audit-logged
 *    with redacted metadata (SUP-R006).
 *  - Support NEVER mutates billing/payment/collection/settlement/entitlement/
 *    onboarding/device state through its own SQL. It reads through the trusted
 *    Sprint 30/31/32/33/34 services/models and only mutates via those governed
 *    services (SUP-R008..R012, SUP-R028). It can never mark an invoice paid
 *    (SUP-R015) or unlock a paid entitlement (SUP-R016).
 *  - Manual suspension (Sprint 25) always wins over any support action; a support
 *    action never silently reactivates a suspended tenant (SUP-R014).
 *  - Device revoke/reactivate uses the Sprint 34 services; a revoked device stays
 *    blocked until a governed reactivation (SUP-R012, SUP-R013).
 *  - Impersonation is DISABLED by default. It is only ever enabled when it is
 *    governed, audited, time-bound and read-only-safe; it never exposes raw
 *    credentials/tokens (SUP-R018, SUP-R019).
 *  - This file holds NO secrets and NO real tenant/customer data; it is safe to
 *    commit and grep in CI. No runtime output (audit, command, smoke, docs, API,
 *    console) may leak secrets or PII (SUP-R007, SUP-R023).
 *
 * See docs/architecture/sprint-35-support-console-tenant-operations-incident-diagnostics.md
 * and docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */

return [
    // SUP-R001 — the support console is enabled for platform.admin only.
    'console_enabled' => env('SUPPORT_CONSOLE_ENABLED', true),

    // SUP-R004 — the console is read-only by default. Mutating support flows are
    // opt-in per action and always require a reason code + audit.
    'read_only_by_default' => true,

    // SUP-R005 — a support mutation must carry an explicit reason code. These are
    // the safe, enumerable reason codes support uses; free-form PII is never a
    // valid reason code.
    'reason_required_for_mutation' => true,
    'reason_codes' => [
        'tenant_request',
        'billing_dispute',
        'payment_investigation',
        'entitlement_review',
        'onboarding_assistance',
        'device_lost_or_stolen',
        'device_replacement',
        'device_decommission',
        'sync_failure_investigation',
        'security_incident',
        'fraud_review',
        'internal_review',
        'data_correction_request',
        'other_governed',
    ],

    // SUP-R002 — tenant health is computed only through SupportTenantHealthService.
    // These are the canonical, ordered health statuses (worst last).
    'health_statuses' => ['healthy', 'watch', 'degraded', 'blocked', 'critical'],
    'health_policy' => [
        // Manual suspension always resolves to the worst status (SUP-R014).
        'manual_suspension_status' => 'critical',
        'unpaid_past_grace_status' => 'blocked',
        'unpaid_in_grace_status' => 'degraded',
        'trial_expired_status' => 'degraded',
        'onboarding_failed_status' => 'degraded',
        'sync_failure_status' => 'watch',
        'revoked_device_status' => 'watch',
        // Number of days after due before an unpaid invoice is "past grace".
        'grace_days' => env('SUPPORT_BILLING_GRACE_DAYS', 7),
    ],

    // SUP-R003/R020 — the diagnostic timeline is deterministic, tenant-isolated and
    // sourced only from these audited/ledger sources. No raw payloads.
    'timeline' => [
        'sources' => [
            'onboarding',
            'invoice',
            'collection',
            'payment_intent',
            'gateway_event',
            'entitlement_decision',
            'device_activation',
            'sync_batch',
            'sync_conflict',
            'incident',
            'support_action',
            'admin_audit',
        ],
        'default_limit' => 100,
        'max_limit' => 500,
    ],

    // SUP-R023/R024 — incident note retention/safety policy.
    'incidents' => [
        'categories' => [
            'billing', 'payment', 'entitlement', 'onboarding', 'device',
            'sync', 'android_runtime', 'export', 'performance', 'other',
        ],
        'severities' => ['low', 'medium', 'high', 'critical'],
        'statuses' => [
            'open', 'investigating', 'waiting_tenant', 'waiting_internal',
            'resolved', 'closed', 'cancelled',
        ],
        'note_types' => ['internal', 'tenant_visible', 'system'],
        'redact_note_body' => true,
        // Notes/incidents are retained (soft context) — support never hard-deletes
        // an audit-bearing record.
        'retention_days' => env('SUPPORT_INCIDENT_RETENTION_DAYS', 3650),
    ],

    // SUP-R017 — support read-only context is tenant-scoped and time-bound.
    'read_only_context' => [
        'enabled' => env('SUPPORT_READ_ONLY_CONTEXT_ENABLED', true),
        'default_ttl_minutes' => env('SUPPORT_READ_ONLY_CONTEXT_TTL_MINUTES', 60),
        'max_ttl_minutes' => 240,
    ],

    // SUP-R018/R019 — impersonation is DISABLED by default. It is never turned on
    // unless a governed, audited, time-bound, read-only-safe implementation exists.
    // In this codebase it is intentionally left disabled: the read-only context
    // above covers every safe support-visibility need without ever borrowing a
    // tenant user's identity or credentials.
    'impersonation' => [
        'enabled' => env('SUPPORT_IMPERSONATION_ENABLED', false),
        'read_only_only' => true,
        'default_ttl_minutes' => 15,
        'max_ttl_minutes' => 30,
        'expose_raw_credentials' => false,
        'disabled_reason' => 'Impersonation is disabled by governance; use a read-only support context instead.',
    ],

    // SUP-R012/R013 — device revoke/reactivate support policy. Revoke always uses
    // the Sprint 34 DeviceRevocationService. Reactivation is a governed re-prepare
    // through the Sprint 34 DeviceActivationService (it re-runs the entitlement /
    // device-limit gate); it is disabled by default and never lifts a suspension.
    'device_operations' => [
        'revoke_enabled' => true,
        'reactivate_enabled' => env('SUPPORT_DEVICE_REACTIVATE_ENABLED', false),
        'reactivate_not_supported_reason' => 'Governed device reactivation is disabled; re-activate via the standard device activation flow, which re-runs the entitlement and device-limit gate.',
    ],

    // SUP-R022 — sync failure inspection sources from the Sprint 34 sync ledgers.
    'sync_inspection' => [
        'batch_failure_statuses' => ['failed', 'partial_failed', 'rejected'],
        'item_failure_statuses' => ['failed', 'rejected', 'conflict'],
        'default_limit' => 100,
    ],

    // SUP-R021 — the blocked/denied action explorer sources from audited decisions.
    'blocked_action_explorer' => [
        'sources' => ['tenant_entitlement_decisions', 'tenant_android_sync_items', 'admin_audit_logs'],
        'default_limit' => 100,
    ],

    // SUP-R008/R009/R010/R011 — the read-only viewers must never mutate the state
    // they read. These flags are asserted by the governance audit.
    'viewers_read_only' => [
        'billing' => true,
        'payment' => true,
        'entitlement' => true,
        'onboarding' => true,
        'android_runtime' => true,
    ],

    // SUP-R006/R007/R023 — redaction is required for all support output/metadata.
    'redaction' => [
        'required' => true,
        'redact_metadata' => true,
    ],

    // Support action ledger types (tenant_support_actions.action_type).
    'action_types' => [
        'read_context_started', 'read_context_ended', 'device_revoked',
        'device_reactivated', 'incident_created', 'incident_updated',
        'note_added', 'diagnostic_exported', 'blocked_action_reviewed',
        'sync_failure_reviewed', 'impersonation_denied', 'other',
    ],
    'action_statuses' => ['allowed', 'denied', 'completed', 'failed'],
    'session_types' => ['read_only_context', 'impersonation'],
    'session_statuses' => ['active', 'expired', 'ended', 'denied'],

    // Hard Sprint 35 guardrails. Every flag MUST stay false; a true value forces
    // the go-no-go decision to NO_GO.
    'support_marks_invoice_paid_allowed' => false,
    'support_unlocks_entitlement_allowed' => false,
    'support_bypasses_payment_settlement_allowed' => false,
    'support_lifts_manual_suspension_allowed' => false,
    'support_reactivates_suspended_tenant_allowed' => false,
    'support_mutates_state_without_governed_service_allowed' => false,
    'support_console_public_or_tenant_mutation_allowed' => false,
    'impersonation_enabled_without_governance_allowed' => false,
    'impersonation_exposes_raw_credentials_allowed' => false,
    'support_output_leaks_secret_or_pii_allowed' => false,

    // The Sprint 35 support-ops commands (self-contract surfaced in go-no-go).
    'support_ops_commands' => [
        'support-ops:tenant-health',
        'support-ops:timeline',
        'support-ops:billing-status',
        'support-ops:payment-status',
        'support-ops:entitlement-denials',
        'support-ops:sync-failures',
        'support-ops:incident-summary',
        'support-ops:device-action',
        'support-ops:governance-audit',
        'support-ops:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–34) that must remain
    // registered for the support-ops gate (prior-sprint gate contract, SUP-R029).
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
        'android-runtime:go-no-go',
    ],

    // Required documentation contract (SUP-R030). Paths are repo-root relative.
    'required_docs' => [
        'docs/architecture/sprint-35-support-console-tenant-operations-incident-diagnostics.md',
        'docs/sprints/sprint-35-support-console-tenant-operations-incident-diagnostics-evidence.md',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (SUP-R007/R030).
    'rules' => [
        'SUP-R001' => 'Support console must require platform.admin.',
        'SUP-R002' => 'Tenant health must be computed through SupportTenantHealthService.',
        'SUP-R003' => 'Support diagnostics must be tenant-isolated.',
        'SUP-R004' => 'Support console must be read-only by default.',
        'SUP-R005' => 'Support mutations must require an explicit reason code.',
        'SUP-R006' => 'Support actions must be audit-logged with redacted metadata.',
        'SUP-R007' => 'Support output must not leak secrets/PII.',
        'SUP-R008' => 'Support billing viewer must not mutate invoice/payment/collection state.',
        'SUP-R009' => 'Support payment viewer must not bypass Sprint 31 settlement rules.',
        'SUP-R010' => 'Support entitlement viewer must not bypass Sprint 32 enforcement.',
        'SUP-R011' => 'Support onboarding viewer must not mutate Sprint 33 provisioning lifecycle except governed retry/cancel if explicitly supported.',
        'SUP-R012' => 'Support device revoke/reactivate must use Sprint 34 services.',
        'SUP-R013' => 'A revoked device must remain blocked until a governed reactivation.',
        'SUP-R014' => 'Manual suspension always wins over support actions.',
        'SUP-R015' => 'A support action must not mark an invoice paid.',
        'SUP-R016' => 'A support action must not unlock paid entitlement.',
        'SUP-R017' => 'Support read-only context must be tenant-scoped and time-bound.',
        'SUP-R018' => 'Support impersonation is disabled by default unless governed, audited, time-bound, and read-only-safe.',
        'SUP-R019' => 'Support impersonation must never expose raw credentials/tokens.',
        'SUP-R020' => 'The diagnostic timeline must be deterministic and explainable.',
        'SUP-R021' => 'The blocked/denied action explorer must source from audited decisions/logs.',
        'SUP-R022' => 'Sync failure inspection must source from Sprint 34 sync ledgers.',
        'SUP-R023' => 'Support incident notes must be redacted and tenant-isolated.',
        'SUP-R024' => 'Support incident status changes must be audited.',
        'SUP-R025' => 'Support summaries must use safe reason codes.',
        'SUP-R026' => 'A platform-admin bypass must be explicit and audited.',
        'SUP-R027' => 'No tenant/public support mutation route may exist.',
        'SUP-R028' => 'No direct DB-state repair mutation without a governed service.',
        'SUP-R029' => 'Prior Sprint 24–34 gates must remain green.',
        'SUP-R030' => 'Go/no-go must verify tenant health, timeline, incident notes, support audit, device support flow, sync diagnostics, billing/payment/entitlement/onboarding visibility, and redaction.',
    ],
];
