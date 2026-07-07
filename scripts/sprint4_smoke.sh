#!/usr/bin/env bash
#
# Sprint 4 — Sales Backend Integration smoke test.
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

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md

echo "== Backend database =="
check "sales migration exists" bash -c 'ls backend/database/migrations/*create_sales_table.php'
check "sale_items migration exists" bash -c 'ls backend/database/migrations/*create_sale_items_table.php'
check "payments migration exists" bash -c 'ls backend/database/migrations/*create_payments_table.php'

echo "== Backend models & services =="
check "Sale model exists" test -f backend/app/Models/Sale.php
check "SaleItem model exists" test -f backend/app/Models/SaleItem.php
check "Payment model exists" test -f backend/app/Models/Payment.php
check "InvoiceNumberGenerator exists" test -f backend/app/Services/InvoiceNumberGenerator.php
check "ProductPriceResolver exists" test -f backend/app/Services/ProductPriceResolver.php
check "SaleService exists" test -f backend/app/Services/SaleService.php

echo "== Backend API =="
check "SaleController exists" test -f backend/app/Http/Controllers/Api/V1/SaleController.php
check "SaleCashPaymentController exists" test -f backend/app/Http/Controllers/Api/V1/SaleCashPaymentController.php
check "StoreSaleRequest exists" test -f backend/app/Http/Requests/Api/V1/StoreSaleRequest.php
check "SaleResource exists" test -f backend/app/Http/Resources/Api/V1/SaleResource.php
check "sales routes registered" has "SaleController" backend/routes/api.php
check "cash payment route registered" has "payments/cash" backend/routes/api.php

echo "== Backend tests =="
check "sales api test exists" test -f backend/tests/Feature/SalesApiTest.php
check "cash payment test exists" test -f backend/tests/Feature/CashPaymentApiTest.php
check "sales tenant isolation test exists" test -f backend/tests/Feature/SalesTenantIsolationTest.php
check "invoice number generator test exists" test -f backend/tests/Feature/InvoiceNumberGeneratorTest.php

echo "== Android sales =="
APP=android/app/src/main/java/com/aishtech/poslite
check "Android SalesRepository exists" test -f "$APP/data/repository/SalesRepository.kt"
check "Android sales DTO exists" test -f "$APP/data/remote/dto/SalesDtos.kt"
check "Android PosApiService has sales endpoint" has "api/v1/sales" "$APP/core/network/PosApiService.kt"
check "Android Cashier checkout cash action" has "checkoutCash" "$APP/feature/cashier/CashierViewModel.kt"
check "Cashier layout has checkout button" hasf "buttonCheckout" android/app/src/main/res/layout/activity_cashier.xml

echo "== Android shell integrity =="
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
