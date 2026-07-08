<?php

/**
 * Sprint 22 — Lead Management / Sales Pipeline Readiness Foundation.
 *
 * Governance rules for sales pipeline readiness: canonical pipeline stages,
 * required sales pipeline docs, required signoff roles, blocking/watch risk
 * severities, accepted-risk expiry requirements, hard guardrails, and the
 * cumulative Sprint 13–21 gate commands that must remain registered. Contains no
 * secrets, no real payment gateway credentials, no server credentials, no CRM
 * token, and no real tenant/customer data. Leads are intake/pipeline data only;
 * there is NO automatic tenant/user/subscription/device creation, NO real billing
 * collection, and NO real CRM/WhatsApp/email/Slack sending in Sprint 22. Doc paths
 * are resolved relative to the repository root (base_path('..')) by the services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Canonical pipeline stages that must exist for a GO decision.
    'canonical_stages' => [
        'NEW',
        'CONTACTED',
        'QUALIFIED',
        'DEMO_SCHEDULED',
        'PROPOSAL_SENT',
        'NEGOTIATION',
        'WON_READY_FOR_ONBOARDING',
        'LOST',
        'ARCHIVED',
    ],

    // Human-friendly default stage definitions seeded by ensure-defaults.
    'default_stage_definitions' => [
        ['stage_code' => 'NEW', 'name' => 'New', 'sort_order' => 10, 'is_default' => true, 'is_terminal' => false],
        ['stage_code' => 'CONTACTED', 'name' => 'Contacted', 'sort_order' => 20, 'is_default' => false, 'is_terminal' => false],
        ['stage_code' => 'QUALIFIED', 'name' => 'Qualified', 'sort_order' => 30, 'is_default' => false, 'is_terminal' => false],
        ['stage_code' => 'DEMO_SCHEDULED', 'name' => 'Demo Scheduled', 'sort_order' => 40, 'is_default' => false, 'is_terminal' => false],
        ['stage_code' => 'PROPOSAL_SENT', 'name' => 'Proposal Sent', 'sort_order' => 50, 'is_default' => false, 'is_terminal' => false],
        ['stage_code' => 'NEGOTIATION', 'name' => 'Negotiation', 'sort_order' => 60, 'is_default' => false, 'is_terminal' => false],
        ['stage_code' => 'WON_READY_FOR_ONBOARDING', 'name' => 'Won — Ready for Onboarding Review', 'sort_order' => 70, 'is_default' => false, 'is_terminal' => true],
        ['stage_code' => 'LOST', 'name' => 'Lost', 'sort_order' => 80, 'is_default' => false, 'is_terminal' => true],
        ['stage_code' => 'ARCHIVED', 'name' => 'Archived', 'sort_order' => 90, 'is_default' => false, 'is_terminal' => true],
    ],

    // Sales pipeline documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/sales-pipeline/lead-management-policy.md',
        'docs/sales-pipeline/sales-pipeline-stage-map.md',
        'docs/sales-pipeline/qualification-readiness-checklist.md',
        'docs/sales-pipeline/manual-follow-up-playbook.md',
        'docs/sales-pipeline/onboarding-handover-readiness.md',
        'docs/sales-pipeline/sales-pipeline-risk-register.md',
        'docs/sales-pipeline/sales-pipeline-go-watch-no-go-report.md',
    ],

    // Risk severities that force NO-GO (unless a valid accepted risk) / WATCH.
    'blocking_risk_severities' => ['CRITICAL', 'HIGH'],
    'watch_risk_severities' => ['MEDIUM'],

    // Severities whose accepted risk must carry an expiry/review date + approver.
    'accepted_risk_requires_expiry_for' => ['CRITICAL', 'HIGH', 'MEDIUM'],

    // Signoff roles that must all approve (non-rejected) for a GO decision.
    'required_signoff_roles' => [
        'OWNER',
        'SALES',
        'TECHNICAL',
        'OPERATIONS',
        'LEGAL_PRIVACY',
        'ONBOARDING',
    ],

    // Hard Sprint 22 guardrails.
    'auto_tenant_creation_allowed' => false,
    'auto_user_creation_allowed' => false,
    'auto_subscription_creation_allowed' => false,
    'auto_device_registration_allowed' => false,
    'real_billing_collection_allowed' => false,
    'real_crm_integration_allowed' => false,
    'real_email_sending_allowed' => false,
    'real_whatsapp_sending_allowed' => false,
    'real_alert_sending_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–21 release/pilot/handover/operations/commercial/public
    // website commands that must remain registered for the sales pipeline gate.
    'required_commands' => [
        'production:readiness-check',
        'release:go-no-go',
        'pilot:rc-check',
        'pilot:uat-summary',
        'pilot:deployment-check',
        'pilot:field-trial-summary',
        'pilot:daily-monitoring-check',
        'pilot:health-summary',
        'hypercare:issue-triage',
        'pilot:defect-summary',
        'pilot:burndown-summary',
        'pilot:sla-check',
        'pilot:stabilization-go-no-go',
        'pilot:closure-check',
        'production:handover-summary',
        'production:signoff-summary',
        'production:handover-go-no-go',
        'production:ops-health',
        'production:incident-summary',
        'production:backup-governance-check',
        'production:post-handover-go-no-go',
        'commercial:launch-readiness',
        'commercial:package-summary',
        'commercial:onboarding-capacity',
        'commercial:launch-go-no-go',
        'public-website:readiness',
        'public-website:content-summary',
        'public-website:lead-summary',
        'public-website:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the sales pipeline GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'release_gate' => ['production:readiness-check', 'release:go-no-go'],
        'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
        'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
        'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
        'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
        'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
        'commercial_launch_gate' => ['commercial:launch-readiness', 'commercial:package-summary', 'commercial:onboarding-capacity', 'commercial:launch-go-no-go'],
        'public_website_gate' => ['public-website:readiness', 'public-website:content-summary', 'public-website:lead-summary', 'public-website:go-no-go'],
    ],
];
