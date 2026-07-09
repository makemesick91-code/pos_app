#!/usr/bin/env bash

set -uo pipefail

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

hasf() { grep -q "$1" "$2"; }

BACK=backend
CFG="$BACK/config/import_governance.php"
SVC="$BACK/app/Services/DataImport"
CMD="$BACK/app/Console/Commands"

echo "== Sprint 37 config / rules =="
check "import governance config exists" test -f "$CFG"
check "dry-run default" hasf "'dry_run_default' => true" "$CFG"
check "tenant imports disabled" hasf "'tenant_side_import_enabled' => false" "$CFG"
check "csv supported" hasf "'csv'" "$CFG"
check "xlsx deferred" hasf "deferred_reason" "$CFG"
for i in $(seq -w 1 34); do
  check "config locks IMP-R0$i" hasf "IMP-R0$i" "$CFG"
  check "foundation locks IMP-R0$i" hasf "IMP-R0$i" "$BACK/config/pos_foundation.php"
  check "project rules lock IMP-R0$i" hasf "IMP-R0$i" "docs/PROJECT_RULES.md"
done

echo "== Files wired =="
for s in TenantDataImportService ImportFileParserService ImportTemplateService ImportValidationService ImportIdempotencyService CategoryImportService ProductImportService SupplierImportService CustomerImportService InitialStockImportService PriceImportService PaymentMethodSettingsImportService TenantBootstrapPackService ImportRollbackService ImportAuditService ImportSupportBridgeService ImportObservabilityBridgeService ImportRedactor ImportGovernanceAuditService ImportGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
for c in ImportTemplateCommand ImportValidateCommand ImportExecuteCommand ImportSummaryCommand ImportRowsCommand ImportRollbackCommand ImportBootstrapPackCommand ImportGovernanceAuditCommand ImportGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "admin import routes exist" hasf "prefix('imports')" "$BACK/routes/api.php"
check "no tenant import routes" bash -c "! grep -R \"Route::.*imports\" $BACK/routes/web.php"

echo "== Isolated sqlite command smoke =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint37smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
trap 'rm -f "$SMOKE_DB" /tmp/s37-*.csv' EXIT
php artisan migrate --force >/dev/null 2>&1

FIXTURE="$(php artisan tinker --execute='
use App\Models\Tenant; use App\Models\Store; use App\Models\User;
$tenant = Tenant::factory()->create(["code" => "IMP-SMOKE"]);
$store = Store::factory()->create(["tenant_id" => $tenant->id, "code" => "MAIN"]);
$admin = User::factory()->platformAdmin()->create();
echo $tenant->id." ".$store->id." ".$admin->id." ".$store->code;
' 2>/dev/null)"
TENANT_ID="$(echo "$FIXTURE" | awk '{print $1}')"
STORE_ID="$(echo "$FIXTURE" | awk '{print $2}')"
ADMIN_ID="$(echo "$FIXTURE" | awk '{print $3}')"
STORE_CODE="$(echo "$FIXTURE" | awk '{print $4}')"

cat > /tmp/s37-category.csv <<CSV
name,sort_order
Beverage,1
CSV
cat > /tmp/s37-product.csv <<CSV
sku,name,category,selling_price,cost_price,unit
SKU-S37,Tea,Beverage,12000,8000,cup
CSV
cat > /tmp/s37-stock.csv <<CSV
sku,store_code,qty
SKU-S37,$STORE_CODE,5
CSV
cat > /tmp/s37-price.csv <<CSV
sku,store_code,selling_price
SKU-S37,$STORE_CODE,13000
CSV
cat > /tmp/s37-supplier.csv <<CSV
code,name,email,phone,address
SUP-S37,Acme,supplier@example.test,08123,hidden
CSV
cat > /tmp/s37-customer.csv <<CSV
code,name,email,phone,address
CUS-S37,Jane,jane@example.test,08999,hidden
CSV
cat > /tmp/s37-payment.csv <<CSV
code,name,method_type,is_default
CASH,Cash,cash,1
CSV
cat > /tmp/s37-settings.csv <<CSV
setting_key,setting_value
receipt_footer,Thank you
CSV

for type in category product supplier customer initial_stock price payment_method default_settings bootstrap_pack; do
  check "template $type" php artisan import:template --type="$type"
done

check "validate dry-run no mutation" php artisan import:validate --tenant="$TENANT_ID" --type=category --file=/tmp/s37-category.csv
check "execute without flag remains dry-run" php artisan import:execute --tenant="$TENANT_ID" --type=category --file=/tmp/s37-category.csv --idempotency-key=noexec --reason=bootstrap
check "category execute" php artisan import:execute --tenant="$TENANT_ID" --type=category --file=/tmp/s37-category.csv --idempotency-key=cat --reason=bootstrap --execute --actor="$ADMIN_ID"
check "product execute" php artisan import:execute --tenant="$TENANT_ID" --type=product --file=/tmp/s37-product.csv --idempotency-key=prod --reason=bootstrap --execute --actor="$ADMIN_ID"
check "supplier execute redacted" php artisan import:execute --tenant="$TENANT_ID" --type=supplier --file=/tmp/s37-supplier.csv --idempotency-key=sup --reason=bootstrap --execute --actor="$ADMIN_ID"
check "customer execute redacted" php artisan import:execute --tenant="$TENANT_ID" --type=customer --file=/tmp/s37-customer.csv --idempotency-key=cus --reason=bootstrap --execute --actor="$ADMIN_ID"
check "initial stock execute" php artisan import:execute --tenant="$TENANT_ID" --branch="$STORE_ID" --type=initial_stock --file=/tmp/s37-stock.csv --idempotency-key=stock --reason=bootstrap --execute --actor="$ADMIN_ID"
check "price execute" php artisan import:execute --tenant="$TENANT_ID" --branch="$STORE_ID" --type=price --file=/tmp/s37-price.csv --idempotency-key=price --reason=bootstrap --execute --actor="$ADMIN_ID"
check "payment method execute" php artisan import:execute --tenant="$TENANT_ID" --type=payment_method --file=/tmp/s37-payment.csv --idempotency-key=pay --reason=bootstrap --execute --actor="$ADMIN_ID"
check "default settings execute" php artisan import:execute --tenant="$TENANT_ID" --type=default_settings --file=/tmp/s37-settings.csv --idempotency-key=settings --reason=bootstrap --execute --actor="$ADMIN_ID"
check "idempotent retry same run key" php artisan import:execute --tenant="$TENANT_ID" --type=category --file=/tmp/s37-category.csv --idempotency-key=cat --reason=bootstrap --execute --actor="$ADMIN_ID"
check "summary runs" php artisan import:summary --tenant="$TENANT_ID"
RUN_ID="$(php artisan tinker --execute='echo App\Models\TenantDataImportRun::where("mode","execute")->latest()->value("id");' 2>/dev/null)"
check "rows runs" php artisan import:rows --run="$RUN_ID"
check "rollback dry-run" php artisan import:rollback --run="$RUN_ID"
check "governance audit" php artisan import:governance-audit
check "go/no-go" php artisan import:go-no-go

COUNTS="$(php artisan tinker --execute='
echo "PRODUCTS=".App\Models\Product::count().PHP_EOL;
echo "CATEGORIES=".App\Models\ProductCategory::count().PHP_EOL;
echo "STOCK=".App\Models\InventoryMovement::where("movement_type","OPENING")->count().PHP_EOL;
echo "INVOICE_PAID=".App\Models\TenantBillingInvoice::where("status","paid")->count().PHP_EOL;
' 2>/dev/null)"
echo "$COUNTS" | sed 's/^/  probe: /'
check "product imported once" bash -c "echo \"$COUNTS\" | grep -q 'PRODUCTS=1'"
check "category imported once" bash -c "echo \"$COUNTS\" | grep -q 'CATEGORIES=1'"
check "stock imported once" bash -c "echo \"$COUNTS\" | grep -q 'STOCK=1'"
check "no invoice marked paid" bash -c "echo \"$COUNTS\" | grep -q 'INVOICE_PAID=0'"

OUT_FILE="$(mktemp -t sprint37out.XXXXXX)"
php artisan import:go-no-go --json > "$OUT_FILE" 2>/dev/null
check "no concrete secret leakage" bash -c "! grep -Eiq 'sk_live_|server_key_|private_key_|AKIA[0-9A-Z]' '$OUT_FILE'"
check "no raw CSV leakage" bash -c "! grep -q 'supplier@example.test' '$OUT_FILE'"

cd "$ROOT"
echo "== Result =="
echo "PASS=$pass FAIL=$fail"
if [ "$fail" -ne 0 ]; then
  exit 1
fi
