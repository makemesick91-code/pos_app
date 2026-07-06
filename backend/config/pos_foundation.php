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
    ],
    'sprints' => [
        'sprint_0' => 'Project Setup',
        'sprint_1' => 'SaaS Tenant Foundation',
        'sprint_2' => 'Product Foundation',
    ],
];
