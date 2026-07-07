<?php

/**
 * QRIS payment gateway configuration (Sprint 5).
 *
 * Credentials live ONLY here, sourced from the environment — never in the
 * database, never surfaced to Android. The `fake` provider ships enabled so the
 * whole QRIS flow (create → webhook → status) is exercisable locally and in
 * tests without any external network call. Real providers are disabled by
 * default and act as stubs until merchant onboarding + live credentials exist.
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md sections 13 and 16.
 */
return [
    'default_qris_provider' => env('QRIS_PROVIDER', 'fake'),
    'qris_expiry_minutes' => (int) env('QRIS_EXPIRY_MINUTES', 15),

    'providers' => [
        'fake' => [
            'enabled' => (bool) env('QRIS_FAKE_ENABLED', true),
            'webhook_secret' => env('QRIS_FAKE_WEBHOOK_SECRET', 'local-fake-secret'),
        ],
        'midtrans' => [
            'enabled' => (bool) env('MIDTRANS_ENABLED', false),
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        ],
        'xendit' => [
            'enabled' => (bool) env('XENDIT_ENABLED', false),
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        ],
        'duitku' => [
            'enabled' => (bool) env('DUITKU_ENABLED', false),
            'merchant_code' => env('DUITKU_MERCHANT_CODE'),
            'api_key' => env('DUITKU_API_KEY'),
        ],
    ],
];
