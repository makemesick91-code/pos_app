<?php

/**
 * Sprint 36 — Observability, Health Monitoring, Queue & Production Diagnostics.
 *
 * Canonical, server-side source of truth for how the platform is made
 * production-observable so operational issues are detected before tenants
 * complain — WITHOUT ever mutating tenant/billing/entitlement/onboarding/device
 * state, exposing secrets/PII, or bypassing any prior-sprint gate.
 *
 * Design contract (never weakened by a later sprint):
 *  - Public health endpoints (if enabled) expose only minimal liveness/readiness:
 *    ok/degraded/error + timestamp. No tenant data, no environment secret, no DB
 *    credential, no PII (OBS-R001).
 *  - Admin observability routes require platform.admin (OBS-R002) and are
 *    read-only by default (OBS-R003). The only mutations — anomaly
 *    acknowledge/resolve, alert-suggestion dismiss/accept, and (if ever enabled)
 *    a governed failed-job retry — require a reason code and are audited
 *    (OBS-R010, OBS-R028).
 *  - Diagnostics NEVER mark an invoice paid (OBS-R024), unlock entitlement
 *    (OBS-R025), reactivate a tenant/device (OBS-R026), or bypass a manual
 *    suspension (OBS-R027). Anomaly detection is read-only and sources from the
 *    trusted Sprint 30–35 ledgers (OBS-R013..R019). Incident auto-suggestion
 *    creates SUGGESTIONS only; it never auto-mutates tenant state, and an
 *    accepted suggestion only ever creates a support incident through the Sprint
 *    35 SupportIncidentService, audited (OBS-R018).
 *  - This file holds NO secrets and NO real tenant/customer data; it is safe to
 *    commit and grep in CI. No runtime output (health endpoint, metric payload,
 *    audit, command, smoke, docs, API) may leak secrets or PII (OBS-R004..R009).
 *
 * See docs/architecture/sprint-36-observability-health-monitoring-queue-production-diagnostics.md
 * and docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */

return [
    // OBS-R001 — public health endpoints. Liveness/readiness are minimal and
    // non-tenant by construction; disable per env if a fronting LB owns them.
    'public_liveness_enabled' => env('OBSERVABILITY_PUBLIC_LIVENESS_ENABLED', true),
    'public_readiness_enabled' => env('OBSERVABILITY_PUBLIC_READINESS_ENABLED', true),

    // OBS-R002/R023 — admin observability surface is platform.admin only.
    'admin_observability_enabled' => env('OBSERVABILITY_ADMIN_ENABLED', true),

    // OBS-R003 — the observability surface is read-only by default. The few
    // mutating flows are opt-in per action and always require a reason code.
    'read_only_by_default' => true,
    'reason_required_for_mutation' => true,

    // OBS-R005/R025 — safe, enumerable reason codes for observability mutations
    // (anomaly acknowledge/resolve/ignore, alert dismiss/accept, job retry).
    // Free-form PII is never a valid reason code.
    'reason_codes' => [
        'operator_review',
        'false_positive',
        'expected_maintenance',
        'known_issue',
        'incident_opened',
        'incident_linked',
        'resolved_after_fix',
        'resolved_transient',
        'duplicate_signal',
        'threshold_tuning',
        'governed_retry',
        'internal_review',
        'other_governed',
    ],

    // OBS-R020 — canonical ordered health statuses (worst last). Health results
    // are deterministic and explainable via reason codes.
    'health_statuses' => ['healthy', 'watch', 'degraded', 'blocked', 'critical'],

    // OBS-R008/R011/R029 — config-driven thresholds. All anomaly / staleness /
    // backlog decisions read from here so a tune is a config change, not code.
    'thresholds' => [
        // Queue backlog / age.
        'queue_pending_watch' => env('OBS_QUEUE_PENDING_WATCH', 100),
        'queue_pending_degraded' => env('OBS_QUEUE_PENDING_DEGRADED', 500),
        'queue_oldest_pending_watch_seconds' => env('OBS_QUEUE_OLDEST_WATCH_SECONDS', 300),
        'queue_oldest_pending_degraded_seconds' => env('OBS_QUEUE_OLDEST_DEGRADED_SECONDS', 900),
        // Failed jobs.
        'failed_jobs_watch' => env('OBS_FAILED_JOBS_WATCH', 1),
        'failed_jobs_degraded' => env('OBS_FAILED_JOBS_DEGRADED', 10),
        // Scheduler staleness (a command not seen completing within this window).
        'scheduler_stale_seconds' => env('OBS_SCHEDULER_STALE_SECONDS', 3600),
        'scheduler_long_run_ms' => env('OBS_SCHEDULER_LONG_RUN_MS', 60000),
        // Android sync anomalies (Sprint 34).
        'sync_failed_batch_watch' => env('OBS_SYNC_FAILED_BATCH_WATCH', 1),
        'sync_failed_batch_degraded' => env('OBS_SYNC_FAILED_BATCH_DEGRADED', 5),
        'sync_conflict_rate_watch' => env('OBS_SYNC_CONFLICT_RATE_WATCH', 0.1),
        'sync_duplicate_spike' => env('OBS_SYNC_DUPLICATE_SPIKE', 20),
        'revoked_device_attempt_watch' => env('OBS_REVOKED_DEVICE_ATTEMPT_WATCH', 1),
        // Billing / payment anomalies (Sprint 30/31).
        'billing_grace_days' => env('OBS_BILLING_GRACE_DAYS', 7),
        'payment_failed_event_watch' => env('OBS_PAYMENT_FAILED_EVENT_WATCH', 3),
        'webhook_invalid_signature_watch' => env('OBS_WEBHOOK_INVALID_SIG_WATCH', 3),
        'payment_intent_stuck_minutes' => env('OBS_PAYMENT_INTENT_STUCK_MINUTES', 120),
        // Entitlement anomalies (Sprint 32).
        'entitlement_denial_watch' => env('OBS_ENTITLEMENT_DENIAL_WATCH', 20),
        'entitlement_denial_degraded' => env('OBS_ENTITLEMENT_DENIAL_DEGRADED', 100),
        // Onboarding anomalies (Sprint 33).
        'onboarding_failed_watch' => env('OBS_ONBOARDING_FAILED_WATCH', 1),
        'onboarding_stuck_minutes' => env('OBS_ONBOARDING_STUCK_MINUTES', 240),
        // Export/report anomalies (Sprint 27–29).
        'export_denial_watch' => env('OBS_EXPORT_DENIAL_WATCH', 10),
    ],

    // OBS-R005/R006/R007 — infrastructure diagnostic policy. These flags assert
    // that infra checks never expose the credential/key/path they probe.
    'infrastructure' => [
        'check_database' => true,
        'check_cache' => true,
        'check_storage' => true,
        'check_config' => true,
        // The storage probe writes a temp key under this safe, non-tenant dir.
        'storage_probe_directory' => 'observability-probe',
        'expose_credentials' => false,
        'expose_cache_values' => false,
        'expose_raw_paths' => false,
    ],

    // OBS-R012 — tenant runtime probes are tenant-isolated and reuse the Sprint 35
    // SupportTenantHealthService for the canonical health computation.
    'tenant_probe' => [
        'enabled' => true,
        'reuse_support_health' => true,
        'default_limit' => 25,
        'max_limit' => 100,
    ],

    // OBS-R013..R019 — anomaly detection sources. Each detector reads ONLY the
    // trusted ledger for its domain; it never mutates that domain's state.
    'anomaly' => [
        'categories' => [
            'queue', 'scheduler', 'billing', 'payment', 'entitlement',
            'onboarding', 'android_sync', 'export_report', 'storage',
            'cache', 'database', 'other',
        ],
        'severities' => ['low', 'medium', 'high', 'critical'],
        'statuses' => ['open', 'acknowledged', 'suggested', 'linked_to_incident', 'resolved', 'ignored'],
        // A scan is dry-run by default; --execute persists observability events
        // and suggestions ONLY. It never mutates any domain state (OBS-R003).
        'persist_requires_execute' => true,
        // Duplicate anomalies (same tenant + anomaly_key) update occurrence_count
        // and last_seen_at instead of inserting a new row.
        'dedupe_by_key' => true,
        'default_lookback_hours' => env('OBS_ANOMALY_LOOKBACK_HOURS', 168),
    ],

    // OBS-R021/R022 — alert readiness is vendor-neutral and CI-safe. No external
    // monitoring service is ever required. Suggestions are recorded locally.
    'alerting' => [
        'vendor_neutral' => true,
        'external_service_required' => false,
        'channels' => ['log', 'suggestion'],
    ],

    // OBS-R018 — incident auto-suggestion policy. A scan creates SUGGESTIONS
    // only; it never auto-creates a support incident. Accepting a suggestion may
    // create a Sprint 35 support incident, but only through SupportIncidentService,
    // with a reason code, audited. It never mutates any other tenant state.
    'incident_suggestion' => [
        'enabled' => true,
        'auto_create_incident_on_scan' => false,
        'allow_accept_create_incident' => true,
        // Minimum anomaly severity that yields a suggestion.
        'min_severity_for_suggestion' => 'medium',
        'default_incident_reason_code' => 'internal_review',
        // Map an anomaly category to a Sprint 35 support incident category.
        'category_map' => [
            'billing' => 'billing',
            'payment' => 'payment',
            'entitlement' => 'entitlement',
            'onboarding' => 'onboarding',
            'android_sync' => 'sync',
            'export_report' => 'export',
            'queue' => 'performance',
            'scheduler' => 'performance',
            'storage' => 'performance',
            'cache' => 'performance',
            'database' => 'performance',
            'other' => 'other',
        ],
    ],

    // OBS-R010 — queue job retry/replay. DISABLED by default. When ever enabled
    // it is platform.admin only, requires a reason code, is audited, and only
    // retries jobs on the idempotency-safe allow-list. If disabled, the retry
    // route returns a governed "not supported" response (never a silent no-op).
    'job_retry' => [
        'enabled' => env('OBSERVABILITY_JOB_RETRY_ENABLED', false),
        'reason_required' => true,
        'idempotent_only' => true,
        // Job classes known to be idempotent (safe to replay). Empty by default.
        'idempotent_job_allowlist' => [],
        'not_supported_reason' => 'Governed queue retry is disabled; investigate the failed job and re-dispatch through the owning domain service, which re-runs its idempotency guard.',
    ],

    // OBS-R004/R009 — redaction is required for all observability output/metadata.
    'redaction' => [
        'required' => true,
        'redact_metadata' => true,
    ],

    // OBS-R028 — every platform-admin diagnostic mutation is audited.
    'audit' => [
        'required' => true,
        'redact_metadata' => true,
    ],

    // Snapshot scope types (observability_health_snapshots.scope_type).
    'snapshot_scopes' => [
        'application', 'tenant', 'queue', 'scheduler', 'billing', 'payment',
        'android_sync', 'export_report', 'support',
    ],

    // Scheduler run statuses (observability_scheduler_runs.status).
    'scheduler_run_statuses' => ['started', 'completed', 'failed', 'skipped'],

    // Alert suggestion statuses (observability_alert_suggestions.status).
    'alert_suggestion_statuses' => ['suggested', 'dismissed', 'accepted', 'linked_to_incident'],

    // Hard Sprint 36 guardrails. Every flag MUST stay false; a true value forces
    // the go-no-go decision to NO_GO.
    'diagnostics_mark_invoice_paid_allowed' => false,
    'diagnostics_unlock_entitlement_allowed' => false,
    'diagnostics_reactivate_tenant_or_device_allowed' => false,
    'diagnostics_bypass_manual_suspension_allowed' => false,
    'diagnostics_mutate_domain_without_governed_service_allowed' => false,
    'observability_public_endpoint_exposes_tenant_or_secret_allowed' => false,
    'observability_output_leaks_secret_or_pii_allowed' => false,
    'incident_suggestion_auto_mutates_tenant_allowed' => false,
    'queue_retry_without_governance_allowed' => false,
    'external_monitoring_vendor_required_in_ci_allowed' => false,

    // The Sprint 36 observability commands (self-contract surfaced in go-no-go).
    'observability_commands' => [
        'observability:health',
        'observability:infra-check',
        'observability:queue-health',
        'observability:failed-jobs',
        'observability:scheduler-health',
        'observability:tenant-probe',
        'observability:anomaly-scan',
        'observability:metrics-summary',
        'observability:alert-suggestions',
        'observability:governance-audit',
        'observability:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–35) that must remain
    // registered for the observability gate (prior-sprint gate contract, OBS-R030).
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
        'support-ops:go-no-go',
    ],

    // Required documentation contract (OBS-R032). Paths are repo-root relative.
    'required_docs' => [
        'docs/architecture/sprint-36-observability-health-monitoring-queue-production-diagnostics.md',
        'docs/sprints/sprint-36-observability-health-monitoring-queue-production-diagnostics-evidence.md',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (OBS-R004/R032).
    'rules' => [
        'OBS-R001' => 'Public health endpoints must expose minimal non-tenant liveness/readiness only.',
        'OBS-R002' => 'Admin observability routes must require platform.admin.',
        'OBS-R003' => 'Observability diagnostics must be read-only by default.',
        'OBS-R004' => 'Observability output must not leak secrets/PII.',
        'OBS-R005' => 'Database health check must not expose credentials/query payloads.',
        'OBS-R006' => 'Cache health check must not expose keys/values.',
        'OBS-R007' => 'Storage health check must not expose file paths containing tenant/PII unless redacted.',
        'OBS-R008' => 'Queue health must track pending, failed, stale, and long-running risk safely.',
        'OBS-R009' => 'Failed job diagnostics must redact payloads/exceptions.',
        'OBS-R010' => 'Queue retry/replay must be disabled by default or strictly governed, audited, reason-required, and idempotency-aware.',
        'OBS-R011' => 'Scheduler health must detect stale/missed schedules safely.',
        'OBS-R012' => 'Tenant runtime health probes must be tenant-isolated.',
        'OBS-R013' => 'Android sync anomaly detection must source from Sprint 34 ledgers.',
        'OBS-R014' => 'Billing anomaly detection must source from Sprint 30 invoice/collection state.',
        'OBS-R015' => 'Payment webhook anomaly detection must source from Sprint 31 gateway events/intents.',
        'OBS-R016' => 'Entitlement anomaly detection must source from Sprint 32 decision logs/state.',
        'OBS-R017' => 'Onboarding anomaly detection must source from Sprint 33 provisioning runs/steps.',
        'OBS-R018' => 'Support incident suggestion must integrate with Sprint 35 support incidents without auto-mutating tenant state silently.',
        'OBS-R019' => 'Export/report anomaly detection must preserve Sprint 27–29 metering/governance.',
        'OBS-R020' => 'Health summary must be deterministic and explainable with reason codes.',
        'OBS-R021' => 'Alert readiness must be vendor-neutral and CI-safe.',
        'OBS-R022' => 'No external monitoring service required in CI.',
        'OBS-R023' => 'Metrics endpoints must be admin-only unless explicitly safe/minimal.',
        'OBS-R024' => 'Diagnostics must not mark an invoice paid.',
        'OBS-R025' => 'Diagnostics must not unlock entitlement.',
        'OBS-R026' => 'Diagnostics must not reactivate tenant/device.',
        'OBS-R027' => 'Diagnostics must not bypass manual suspension.',
        'OBS-R028' => 'Platform-admin diagnostic actions must be audited.',
        'OBS-R029' => 'Anomaly thresholds must be config-driven.',
        'OBS-R030' => 'Prior Sprint 24–35 gates must remain green.',
        'OBS-R031' => 'No direct DB repair mutation without a governed service.',
        'OBS-R032' => 'Go/no-go must verify health, queue, scheduler, tenant probes, anomaly detection, incident suggestions, audit, and redaction.',
    ],
];
