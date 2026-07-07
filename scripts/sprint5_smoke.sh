#!/usr/bin/env bash
#
# Sprint 5 — QRIS Payment Gateway Foundation smoke test.
# Structural validation only; does not build the Android app or run a database.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

pass=0
fail=0

check() {
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then
    echo "  ok   - $desc"
    pass=$((pass + 1))
  else
    echo "  FAIL - $desc"
    fail=$((fail + 1))
  fi
}

# Custom grep helpers (silent).
has() { grep -Rq "$1" "$2"; }
hasf() { grep -q "$1" "$2"; }

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "sprint 0 evidence exists" test -f docs/sprints/sprint-0-project-setup.md
check "sprint 1 evidence exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "sprint 2 evidence exists" test -f docs/sprints/sprint-2-product-foundation.md
check "sprint 3 evidence exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md
check "sprint 4 evidence exists" test -f docs/sprints/sprint-4-sales-backend-integration.md
check "sprint 5 evidence exists" test -f docs/sprints/sprint-5-qris-payment-gateway-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" hasf "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" docs/PROJECT_RULES.md

echo "== Backend database =="
check "payment_webhook_logs migration exists" bash -c 'ls backend/database/migrations/*create_payment_webhook_logs_table.php'
check "payments qris fields migration exists" bash -c 'ls backend/database/migrations/*add_qris_fields_to_payments_table.php'
check "PaymentWebhookLog model exists" test -f backend/app/Models/PaymentWebhookLog.php

echo "== Backend config =="
check "payment_gateway config exists" test -f backend/config/payment_gateway.php
check "config has fake provider" hasf "'fake'" backend/config/payment_gateway.php
check "env example has QRIS_PROVIDER" hasf "QRIS_PROVIDER" backend/.env.example

echo "== Backend gateway abstraction =="
check "QrisGateway contract exists" test -f backend/app/Services/Payments/Contracts/QrisGateway.php
check "FakeQrisGateway exists" test -f backend/app/Services/Payments/Gateways/FakeQrisGateway.php
check "Midtrans stub exists" test -f backend/app/Services/Payments/Gateways/MidtransQrisGateway.php
check "Xendit stub exists" test -f backend/app/Services/Payments/Gateways/XenditQrisGateway.php
check "Duitku stub exists" test -f backend/app/Services/Payments/Gateways/DuitkuQrisGateway.php
check "QrisGatewayManager exists" test -f backend/app/Services/Payments/QrisGatewayManager.php
check "QrisPaymentService exists" test -f backend/app/Services/Payments/QrisPaymentService.php
check "QrisWebhookService exists" test -f backend/app/Services/Payments/QrisWebhookService.php
check "PaymentStatusSynchronizer exists" test -f backend/app/Services/Payments/PaymentStatusSynchronizer.php

echo "== Backend API =="
check "QrisPaymentController exists" test -f backend/app/Http/Controllers/Api/V1/QrisPaymentController.php
check "PaymentStatusController exists" test -f backend/app/Http/Controllers/Api/V1/PaymentStatusController.php
check "PaymentWebhookController exists" test -f backend/app/Http/Controllers/Api/V1/PaymentWebhookController.php
check "StoreQrisPaymentRequest exists" test -f backend/app/Http/Requests/Api/V1/StoreQrisPaymentRequest.php
check "QrisPaymentResource exists" test -f backend/app/Http/Resources/Api/V1/QrisPaymentResource.php
check "qris payment route registered" has "payments/qris" backend/routes/api.php
check "payment status route registered" has "payments/{payment}/status" backend/routes/api.php
check "webhook route registered" has "webhooks/payments/{provider}" backend/routes/api.php

echo "== Backend reconciliation command =="
check "ReconcilePaymentsCommand exists" test -f backend/app/Console/Commands/ReconcilePaymentsCommand.php
check "reconcile signature present" has "payments:reconcile" backend/app/Console/Commands/ReconcilePaymentsCommand.php

echo "== Backend tests =="
check "QRIS payment api test exists" test -f backend/tests/Feature/QrisPaymentApiTest.php
check "QRIS webhook test exists" test -f backend/tests/Feature/QrisWebhookTest.php
check "payment status api test exists" test -f backend/tests/Feature/PaymentStatusApiTest.php
check "reconciliation command test exists" test -f backend/tests/Feature/PaymentReconciliationCommandTest.php
check "QRIS tenant isolation test exists" test -f backend/tests/Feature/QrisTenantIsolationTest.php
check "FakeQrisGateway unit test exists" test -f backend/tests/Unit/FakeQrisGatewayTest.php

echo "== Android QRIS =="
APP=android/app/src/main/java/com/aishtech/poslite
check "Android QrisRepository exists" test -f "$APP/data/repository/QrisRepository.kt"
check "Android QRIS DTO exists" test -f "$APP/data/remote/dto/QrisPaymentDtos.kt"
check "Android QrisPaymentActivity exists" test -f "$APP/feature/qris/QrisPaymentActivity.kt"
check "Android QrisPaymentViewModel exists" test -f "$APP/feature/qris/QrisPaymentViewModel.kt"
check "Android QRIS layout exists" test -f android/app/src/main/res/layout/activity_qris_payment.xml
check "Android PosApiService has QRIS endpoint" has "payments/qris" "$APP/core/network/PosApiService.kt"
check "Android PosApiService has status endpoint" has "payments/{id}/status" "$APP/core/network/PosApiService.kt"
check "Android QRIS activity registered in manifest" hasf "QrisPaymentActivity" android/app/src/main/AndroidManifest.xml

echo "== Android shell integrity =="
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'

echo "== Security: no gateway secrets in Android =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
