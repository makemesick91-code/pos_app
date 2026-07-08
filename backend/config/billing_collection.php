<?php

/**
 * Sprint 23 — Billing Collection Governance Foundation.
 *
 * Governance rules for SaaS billing collection readiness: default currency, the
 * invoice/payment-evidence lifecycle vocabulary, required billing collection docs,
 * required sign-off roles, blocking/watch risk severities, accepted-risk expiry
 * requirements, hard guardrails, and the cumulative Sprint 13–22 gate commands that
 * must remain registered.
 *
 * SaaS billing collection is platform-to-tenant governance and MUST NOT be mixed
 * with tenant POS cashier/customer payments. In Sprint 23 there is NO real payment
 * gateway, NO auto-charge, NO subscription payment automation, NO auto tenant
 * suspension, NO auto subscription renewal, NO public payment link, and NO real
 * invoice email / WhatsApp / Slack / CRM / accounting integration. Contains no
 * secrets, no payment gateway credentials, and no real tenant/customer data. Doc
 * paths are resolved relative to the repository root (base_path('..')).
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    'currency' => 'IDR',

    'invoice_statuses' => [
        'DRAFT',
        'ISSUED',
        'PARTIAL',
        'PAID',
        'OVERDUE',
        'DISPUTED',
        'VOIDED',
        'ARCHIVED',
    ],

    'payment_evidence_statuses' => [
        'SUBMITTED',
        'UNDER_REVIEW',
        'ACCEPTED',
        'REJECTED',
        'VOIDED',
    ],

    // Billing collection documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/billing-collection/billing-collection-policy.md',
        'docs/billing-collection/manual-payment-evidence-policy.md',
        'docs/billing-collection/invoice-lifecycle-map.md',
        'docs/billing-collection/manual-collection-playbook.md',
        'docs/billing-collection/overdue-dispute-governance.md',
        'docs/billing-collection/billing-risk-register.md',
        'docs/billing-collection/billing-collection-go-watch-no-go-report.md',
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
    ],

    // Hard Sprint 23 guardrails. Every automation flag MUST stay false; a true
    // value forces the readiness/go-no-go decision to NO_GO.
    'real_payment_gateway_allowed' => false,
    'auto_charge_allowed' => false,
    'subscription_payment_automation_allowed' => false,
    'auto_tenant_suspension_allowed' => false,
    'auto_subscription_renewal_allowed' => false,
    'public_payment_link_allowed' => false,
    'real_invoice_email_sending_allowed' => false,
    'real_whatsapp_sending_allowed' => false,
    'real_crm_integration_allowed' => false,
    'real_accounting_integration_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–22 release/pilot/handover/operations/commercial/public
    // website/sales pipeline commands that must remain registered for the billing
    // collection gate.
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
    ],

    // Prior-sprint gate contract surfaced in the billing collection GO/WATCH/NO-GO.
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
    ],
];
