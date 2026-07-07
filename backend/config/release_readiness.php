<?php

/**
 * Sprint 13 — Production Readiness & Release Hardening Foundation.
 *
 * Release gate rules (no secrets here). Paths are resolved relative to the
 * repository root (base_path('..')) by ReleaseGateService, because the Laravel
 * app lives in backend/ while docs/scripts live at the repository root.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Docs that must exist for a release to be GO.
    'required_docs' => [
        'docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md',
        'docs/PROJECT_RULES.md',
        'docs/release/production-readiness-checklist.md',
        'docs/release/backup-restore-runbook.md',
        'docs/release/release-go-no-go-runbook.md',
        'docs/sprints/sprint-13-production-readiness-release-hardening-foundation.md',
    ],

    // API route URIs that must remain registered (regression contract).
    'required_routes' => [
        'api/health',
        'api/v1/auth/login',
        'api/v1/subscription/status',
        'api/v1/devices',
        'api/v1/sync/products',
        'api/v1/sync/categories',
        'api/v1/sales',
        'api/v1/reports/daily-sales',
        'api/v1/closings/daily',
        'api/v1/admin/tenants',
        'api/v1/admin/tenant-onboarding',
    ],

    // Artisan commands that must remain registered.
    'required_commands' => [
        'production:readiness-check',
        'release:go-no-go',
    ],

    // Files that must NOT be tracked by git (checked against `git ls-files`).
    // gradle-wrapper.jar is the single allowed committed binary exception.
    'forbidden_files' => [
        '.env',
        '*.apk',
        '*.aab',
        '*.keystore',
        '*.jks',
        'database.sqlite',
    ],
];
