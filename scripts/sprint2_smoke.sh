#!/usr/bin/env bash
#
# Sprint 2 — Product Foundation smoke test.
# Structural validation only; does not require a running database.
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

# Custom grep-in-file helper (silent).
has() { grep -Rqs "$1" "$2"; }

echo "Sprint 2 smoke test"
echo "-------------------"

# Documentation & foundation
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "Sprint 0 evidence doc exists" test -f docs/sprints/sprint-0-project-setup.md
check "Sprint 1 evidence doc exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "Sprint 2 evidence doc exists" test -f docs/sprints/sprint-2-product-foundation.md

# Application rules lock
check "PROJECT_RULES Foundation Lock Index" has "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 0 Runtime Rule" has "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 1 Multi-Tenant Runtime Rule" has "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 2 Product Foundation Runtime Rule" has "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "README references Sprint 2" has "Sprint 2" README.md

# Migrations
check "product_categories migration exists" bash -c 'ls backend/database/migrations/*create_product_categories_table.php'
check "products migration exists" bash -c 'ls backend/database/migrations/*create_products_table.php'
check "product_store_prices migration exists" bash -c 'ls backend/database/migrations/*create_product_store_prices_table.php'

# Models
check "ProductCategory model exists" test -f backend/app/Models/ProductCategory.php
check "Product model exists" test -f backend/app/Models/Product.php
check "ProductStorePrice model exists" test -f backend/app/Models/ProductStorePrice.php

# Controllers
check "ProductCategoryController exists" test -f backend/app/Http/Controllers/Api/V1/ProductCategoryController.php
check "ProductController exists" test -f backend/app/Http/Controllers/Api/V1/ProductController.php
check "ProductStorePriceController exists" test -f backend/app/Http/Controllers/Api/V1/ProductStorePriceController.php
check "ProductSyncController exists" test -f backend/app/Http/Controllers/Api/V1/ProductSyncController.php

# Routes
check "product category routes exist" has "product-categories" backend/routes/api.php
check "product routes exist" has "ProductController" backend/routes/api.php
check "product store price routes exist" has "product-store-prices" backend/routes/api.php
check "product sync routes exist" has "sync/products" backend/routes/api.php
check "category sync route exists" has "sync/categories" backend/routes/api.php

# Foundation config
check "pos_foundation config exists" test -f backend/config/pos_foundation.php

# Tests
check "ProductCategoryApiTest exists" test -f backend/tests/Feature/ProductCategoryApiTest.php
check "ProductApiTest exists" test -f backend/tests/Feature/ProductApiTest.php
check "ProductStorePriceApiTest exists" test -f backend/tests/Feature/ProductStorePriceApiTest.php
check "ProductSyncApiTest exists" test -f backend/tests/Feature/ProductSyncApiTest.php
check "ProductTenantIsolationTest exists" test -f backend/tests/Feature/ProductTenantIsolationTest.php

# Hygiene: forbidden files must not be committed
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor committed" bash -c '! git ls-files | grep -qE "(^|/)vendor/"'
check "no node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)node_modules/"'
check "no sqlite/db file committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo "-------------------"
echo "pass: $pass  fail: $fail"

if [ "$fail" -ne 0 ]; then
  exit 1
fi
