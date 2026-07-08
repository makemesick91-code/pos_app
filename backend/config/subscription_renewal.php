<?php

/**
 * Sprint 24 — Subscription Renewal & Dunning Governance Foundation.
 *
 * Governance rules for subscription renewal and dunning readiness: the default
 * manual renewal policy, the candidate/stage vocabulary, required renewal docs,
 * required sign-off roles, blocking/watch risk severities, hard automation
 * guardrails, and the cumulative Sprint 13–23 gate commands that must remain
 * registered.
 *
 * Subscription renewal & dunning is lifecycle governance over TenantSubscription.
 * It is NEVER mixed with tenant POS cashier/customer QRIS/cash payments and is
 * distinct from the Sprint 23 SaaS billing collection invoice/payment-evidence
 * domain. In Sprint 24 there is NO real payment gateway, NO auto-charge, NO
 * subscription payment automation, NO auto tenant suspension/reactivation, NO
 * auto subscription renewal without an explicit manual admin decision, NO auto
 * plan/device-limit change, NO public renewal portal / payment link, and NO real
 * email / WhatsApp / SMS / Slack / CRM / accounting integration. Contains no
 * secrets and no real tenant/customer data. Doc paths resolve relative to the
 * repository root (base_path('..')).
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    'default_policy' => [
        'code' => 'DEFAULT_MANUAL_RENEWAL',
        'name' => 'Default Manual Renewal Governance',
        'renewal_window_days' => 14,
        'grace_period_days' => 7,
        'dunning_start_days_before_expiry' => 7,
        'max_manual_dunning_notices' => 3,
        'requires_manual_approval' => true,
    ],

    'candidate_statuses' => [
        'NEW',
        'IN_REVIEW',
        'DUNNING_PENDING',
        'DUNNING_IN_PROGRESS',
        'PAYMENT_PENDING',
        'READY_FOR_MANUAL_RENEWAL',
        'MANUALLY_RENEWED',
        'GRACE_REVIEW',
        'OVERDUE_REVIEW',
        'DO_NOT_RENEW',
        'ARCHIVED',
    ],

    'renewal_stages' => [
        'NOT_DUE',
        'RENEWAL_WINDOW',
        'GRACE_PERIOD',
        'OVERDUE',
        'MANUAL_REVIEW',
        'CLOSED',
    ],

    // Subscription renewal documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/subscription-renewal/subscription-renewal-policy.md',
        'docs/subscription-renewal/dunning-manual-notice-policy.md',
        'docs/subscription-renewal/renewal-lifecycle-map.md',
        'docs/subscription-renewal/grace-overdue-governance.md',
        'docs/subscription-renewal/manual-renewal-decision-playbook.md',
        'docs/subscription-renewal/subscription-renewal-risk-register.md',
        'docs/subscription-renewal/subscription-renewal-go-watch-no-go-report.md',
    ],

    // Risk severities that force NO-GO (unless a valid accepted risk) / WATCH.
    'blocking_risk_severities' => ['CRITICAL', 'HIGH'],
    'watch_risk_severities' => ['MEDIUM'],

    // Severities whose accepted risk must carry an expiry/review date + approver.
    'accepted_risk_requires_expiry_for' => ['CRITICAL', 'HIGH', 'MEDIUM'],

    // Sign-off roles that must all approve (non-rejected) for a GO decision.
    'required_signoff_roles' => [
        'OWNER',
        'FINANCE',
        'SALES',
        'OPERATIONS',
        'LEGAL_PRIVACY',
        'TECHNICAL',
        'SUPPORT',
    ],

    // Hard Sprint 24 guardrails. Every automation flag MUST stay false; a true
    // value forces the readiness/go-no-go decision to NO_GO.
    'real_payment_gateway_allowed' => false,
    'auto_charge_allowed' => false,
    'subscription_payment_automation_allowed' => false,
    'auto_tenant_suspension_allowed' => false,
    'auto_tenant_reactivation_allowed' => false,
    'auto_subscription_renewal_allowed' => false,
    'auto_plan_change_allowed' => false,
    'auto_device_limit_change_allowed' => false,
    'public_renewal_portal_allowed' => false,
    'public_payment_link_allowed' => false,
    'real_email_sending_allowed' => false,
    'real_whatsapp_sending_allowed' => false,
    'real_sms_sending_allowed' => false,
    'real_alert_sending_allowed' => false,
    'real_crm_integration_allowed' => false,
    'real_accounting_integration_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–23 release/pilot/handover/operations/commercial/public
    // website/sales pipeline/billing collection commands that must remain
    // registered for the subscription renewal gate.
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
        'sales-pipeline:readiness',
        'sales-pipeline:lead-summary',
        'sales-pipeline:activity-summary',
        'sales-pipeline:go-no-go',
        'billing-collection:readiness',
        'billing-collection:invoice-summary',
        'billing-collection:collection-summary',
        'billing-collection:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the subscription renewal GO/WATCH/NO-GO.
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
        'sales_pipeline_gate' => ['sales-pipeline:readiness', 'sales-pipeline:lead-summary', 'sales-pipeline:activity-summary', 'sales-pipeline:go-no-go'],
        'billing_collection_gate' => ['billing-collection:readiness', 'billing-collection:invoice-summary', 'billing-collection:collection-summary', 'billing-collection:go-no-go'],
    ],
];
