<?php

/**
 * Foundation metadata (not runtime logic). Locks the canonical document and
 * the cumulative sprint rules so tooling/tests can assert they are wired.
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    'foundation_document' => 'docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md',
    'project' => 'Aish POS Lite',
    'rules' => [
        'multi_tenant' => true,
        'backend_payment_only' => true,
        'android_lightweight' => true,
        'offline_cash_only' => true,
        'qris_online_only' => true,
        'tenant_isolation_required' => true,
        'product_sync_required' => true,
        'android_cashier_foundation_required' => true,
        'android_local_catalog_required' => true,
        'sales_backend_required' => true,
        'cash_payment_backend_required' => true,
        'qris_not_in_sprint_4' => true,
        'qris_backend_driven_required' => true,
        'qris_webhook_ready_required' => true,
        'payment_gateway_credentials_backend_only' => true,
        'receipt_backend_authoritative' => true,
        'escpos_android_formatter_required' => true,
        'android_gradle_wrapper_required' => true,
        'android_build_ci_required' => true,
    ],
    'sprints' => [
        'sprint_0' => 'Project Setup',
        'sprint_1' => 'SaaS Tenant Foundation',
        'sprint_2' => 'Product Foundation',
        'sprint_3' => 'Android Cashier Foundation',
        'sprint_4' => 'Sales Backend Integration',
        'sprint_5' => 'QRIS Payment Gateway Foundation',
        'sprint_6' => 'Printer & Receipt Foundation',
    ],
];
