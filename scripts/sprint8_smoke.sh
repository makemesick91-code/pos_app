#!/usr/bin/env bash
#
# Sprint 8 — Inventory Simple Foundation smoke test.
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

APP=android/app/src/main/java/com/aishtech/poslite
TEST=android/app/src/test/java/com/aishtech/poslite

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "sprint 0 evidence exists" test -f docs/sprints/sprint-0-project-setup.md
check "sprint 1 evidence exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "sprint 2 evidence exists" test -f docs/sprints/sprint-2-product-foundation.md
check "sprint 3 evidence exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md
check "sprint 4 evidence exists" test -f docs/sprints/sprint-4-sales-backend-integration.md
check "sprint 5 evidence exists" test -f docs/sprints/sprint-5-qris-payment-gateway-foundation.md
check "sprint 6 evidence exists" test -f docs/sprints/sprint-6-printer-receipt-foundation.md
check "sprint 7 evidence exists" test -f docs/sprints/sprint-7-offline-cash-sync-foundation.md
check "sprint 8 evidence exists" test -f docs/sprints/sprint-8-inventory-simple-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" hasf "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 6 Printer & Receipt Foundation Runtime Rule" hasf "Sprint 6 Printer & Receipt Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 7 Offline Cash & Sync Foundation Runtime Rule" hasf "Sprint 7 Offline Cash & Sync Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 8 Inventory Simple Foundation Runtime Rule" hasf "Sprint 8 Inventory Simple Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 8" hasf "sprint-8-inventory-simple-foundation.md" docs/PROJECT_RULES.md

echo "== Backend inventory ledger =="
check "inventory_movements migration exists" bash -c 'ls backend/database/migrations/*create_inventory_movements_table.php'
check "InventoryMovement model exists" test -f backend/app/Models/InventoryMovement.php
check "InventoryMovementService exists" test -f backend/app/Services/Inventory/InventoryMovementService.php
check "StockCalculator exists" test -f backend/app/Services/Inventory/StockCalculator.php
check "inventory current stock controller exists" test -f backend/app/Http/Controllers/Api/V1/InventoryCurrentStockController.php
check "inventory movement controller exists" test -f backend/app/Http/Controllers/Api/V1/InventoryMovementController.php
check "inventory adjustment controller exists" test -f backend/app/Http/Controllers/Api/V1/InventoryAdjustmentController.php
check "current stock request exists" test -f backend/app/Http/Requests/Api/V1/IndexCurrentStockRequest.php
check "inventory movement request exists" test -f backend/app/Http/Requests/Api/V1/IndexInventoryMovementRequest.php
check "inventory adjustment request exists" test -f backend/app/Http/Requests/Api/V1/StoreInventoryAdjustmentRequest.php
check "current stock resource exists" test -f backend/app/Http/Resources/Api/V1/CurrentStockResource.php
check "inventory movement resource exists" test -f backend/app/Http/Resources/Api/V1/InventoryMovementResource.php

echo "== Backend inventory routes =="
check "current-stock route exists" has "inventory/current-stock" backend/routes/api.php
check "product stock route exists" has "inventory/products/{product}/stock" backend/routes/api.php
check "movements route exists" has "inventory/movements" backend/routes/api.php
check "adjustments route exists" has "inventory/adjustments" backend/routes/api.php

echo "== Backend ledger integrity =="
check "stock derived from signed_qty sum" has "signed_qty" backend/app/Services/Inventory/StockCalculator.php
check "SALE_OUT created from sale item" has "createSaleOutForSaleItem" backend/app/Services/SaleService.php
check "movement has SALE_OUT type" has "TYPE_SALE_OUT" backend/app/Models/InventoryMovement.php
check "adjustment types restrict SALE_OUT out" has "ADJUSTMENT_TYPES" backend/app/Models/InventoryMovement.php
check "config marks inventory_ledger_only" hasf "inventory_ledger_only" backend/config/pos_foundation.php
check "config marks sale_out_movement_required" hasf "sale_out_movement_required" backend/config/pos_foundation.php
check "config lists sprint_8" hasf "sprint_8" backend/config/pos_foundation.php

echo "== Backend inventory tests =="
check "inventory adjustment tests exist" test -f backend/tests/Feature/InventoryAdjustmentApiTest.php
check "current stock tests exist" test -f backend/tests/Feature/CurrentStockApiTest.php
check "sale out tests exist" test -f backend/tests/Feature/InventorySaleOutTest.php
check "inventory tenant isolation tests exist" test -f backend/tests/Feature/InventoryTenantIsolationTest.php
check "inventory idempotency tests exist" test -f backend/tests/Feature/InventoryIdempotencyTest.php

echo "== No mutable current_stock source of truth =="
check "no current_stock column in products migration" bash -c '! grep -RE "current_stock" backend/database/migrations'
check "no current_stock field in Product model" bash -c '! grep -E "current_stock" backend/app/Models/Product.php'

echo "== Android Gradle wrapper =="
check "gradlew exists" test -f android/gradlew
check "gradlew executable" test -x android/gradlew
check "gradlew.bat exists" test -f android/gradlew.bat
check "gradle-wrapper.jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "gradle-wrapper.properties exists" test -f android/gradle/wrapper/gradle-wrapper.properties

echo "== Android shell integrity =="
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'

echo "== Android stock visibility =="
check "StockDtos exists" test -f "$APP/data/remote/dto/StockDtos.kt"
check "StockRepository exists" test -f "$APP/data/repository/StockRepository.kt"
check "StockDisplay helper exists" test -f "$APP/feature/cashier/StockDisplay.kt"
check "PosApiService has current-stock endpoint" has "inventory/current-stock" "$APP/core/network/PosApiService.kt"
check "PosApiService has product stock endpoint" has "inventory/products" "$APP/core/network/PosApiService.kt"
check "ServiceLocator wires stockRepository" has "stockRepository" "$APP/core/ServiceLocator.kt"
check "cashier product UI has stock label" hasf "textStock" android/app/src/main/res/layout/item_product.xml
check "cashier stock string exists" hasf "cashier_stock_unknown" android/app/src/main/res/values/strings.xml
check "product UI shows Stok label" bash -c 'grep -Rq "Stok" android/app/src/main/res android/app/src/main/java/com/aishtech/poslite'
check "adapter renders stock labels" has "setStockLabels" "$APP/feature/cashier/ProductListAdapter.kt"

echo "== Android stock tests =="
check "stock dto mapping test exists" test -f "$TEST/StockDtoMappingTest.kt"
check "stock repository test exists" test -f "$TEST/StockRepositoryTest.kt"

echo "== CI workflow =="
check "sprint8-ci workflow exists" test -f .github/workflows/sprint8-ci.yml
check "sprint8-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint8-ci.yml
check "sprint8-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint8-ci.yml

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
