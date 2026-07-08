<?php

/**
 * Sprint 28 — Usage Ledger Anomaly Detection & Governed Repair Foundation.
 *
 * Canonical governance definition for detecting anomalies in the append-only
 * tenant_usage_events ledger (Sprint 27) and repairing effective usage safely.
 * Detection is read-only (ULR-R001/R002). Governed repair defaults to dry-run and
 * requires explicit apply intent + reason + actor + audit log (ULR-R007/R008). The
 * runtime NEVER exposes an update/delete route for ledger events (ULR-R009);
 * corrections are governed repair records that keep the ledger append-only
 * (ULR-R010) and can never drive effective usage negative (ULR-R013). See
 * docs/architecture/usage-ledger-anomaly-detection-governed-repair-governance.md
 * and docs/PROJECT_RULES.md. Contains no secrets and no real tenant/customer data.
 * Doc paths resolve relative to the repository root (base_path('..')).
 */
return [
    // Severity vocabulary (mirrors UsageLedgerAnomalySeverity).
    'severities' => ['critical', 'warning', 'info'],

    // Only these anomaly types may ever be auto-repaired; everything else is
    // manual-review-only (ULR-R010).
    'auto_repairable_types' => ['duplicate_idempotency'],

    // Metadata key fragments that must never appear in a usage event; detection
    // reports the offending KEY names only, never the values (ULR-R006).
    'dangerous_metadata_fragments' => [
        'password', 'token', 'secret', 'credential', 'authorization',
        'card', 'cvv', 'payment_key', 'api_key', 'private_key', 'pin', 'otp',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (ULR-R015/R016)
    // and mirrored in docs/PROJECT_RULES.md.
    'rules' => [
        'ULR-R001' => 'Usage ledger anomaly detection must be server-side and must read from the canonical usage event ledger.',
        'ULR-R002' => 'Anomaly detection must be read-only and must not mutate ledger data.',
        'ULR-R003' => 'Duplicate idempotency anomalies must be detected to protect usage counts from retry/double-count drift.',
        'ULR-R004' => 'Missing required ledger fields and invalid period keys must be detected by governance checks.',
        'ULR-R005' => 'Unknown meter keys must be detected against the canonical usage limit registry.',
        'ULR-R006' => 'Metadata anomaly checks must be redacted and must never print secret values.',
        'ULR-R007' => 'Governed repair must default to dry-run and require explicit apply intent.',
        'ULR-R008' => 'Governed repair apply requires reason, actor, audit log, and redacted metadata.',
        'ULR-R009' => 'Normal runtime must not expose update/delete routes for usage ledger events.',
        'ULR-R010' => 'Ledger repair must preserve append-only behavior by using correction events or governed repair records instead of mutating original runtime events.',
        'ULR-R011' => 'Repair operations must be idempotent and must not create duplicate correction drift.',
        'ULR-R012' => 'Admin anomaly visibility must be platform-admin only and must not leak cross-tenant usage data to normal tenants.',
        'ULR-R013' => 'Effective usage after repair must not become negative.',
        'ULR-R014' => 'reports.exports.monthly must remain meterable from the usage event ledger after Sprint 28.',
        'ULR-R015' => 'Sprint 28 GO requires usage-ledger:go-no-go green.',
        'ULR-R016' => 'Sprint 28 rules must coexist with Sprint 25 TLS-R001..R010, Sprint 26 TPE-R001..R012, and Sprint 27 UEL-R001..R015.',
    ],

    // Hard Sprint 28 guardrails. Every flag MUST stay false; a true value forces
    // the readiness/go-no-go decision to NO_GO.
    'anomaly_detector_may_mutate_ledger_allowed' => false,
    'repair_apply_without_dry_run_default_allowed' => false,
    'repair_apply_without_reason_actor_allowed' => false,
    'usage_ledger_mutation_route_allowed' => false,
    'repair_may_delete_original_event_allowed' => false,
    'effective_usage_negative_allowed' => false,

    // The Sprint 28 commands (self-contract surfaced in go-no-go).
    'usage_ledger_commands' => [
        'usage-ledger:anomaly-scan',
        'usage-ledger:repair-plan',
        'usage-ledger:repair-apply',
        'usage-ledger:repair-summary',
        'usage-ledger:go-no-go',
    ],

    // Prior-sprint gate commands that must remain registered and green.
    'prior_sprint_gates' => [
        'report_export_metering_gate' => ['report-export-metering:go-no-go'],
        'tenant_plan_gate' => ['tenant-plan:go-no-go'],
        'tenant_lifecycle_gate' => ['tenant-lifecycle:go-no-go'],
    ],

    // Sprint 28 documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/usage-ledger-anomaly-detection-governed-repair-governance.md',
        'docs/sprints/sprint-28-usage-ledger-anomaly-detection-governed-repair-foundation.md',
    ],
];
