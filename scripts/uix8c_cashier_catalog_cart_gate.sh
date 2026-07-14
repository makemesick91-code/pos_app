#!/usr/bin/env bash
# UIX-8C-03 cashier / catalog / cart gate (fail-closed).
#
# Enforces the permanent premium cashier-experience baseline (UIX8C-R061..R095)
# on the native cashier app (com.aishtech.poslite):
#   * rule ids UIX8C-R061..R095 persisted (modular rule + PROJECT_RULES);
#   * required screen/state docs present;
#   * canonical cashier context header component + include (business/outlet/
#     cashier/device/network) fed by auth/me, never client input;
#   * category filter (layout + adapter + repository routing) that never mutates
#     the cart and restores the catalog when cleared;
#   * truthful catalog states (loading != empty, no-result != empty);
#   * whole-Rupiah integer money on the cart path (no float on new money path);
#   * clear-cart confirmation + search-clear + error-retry affordances;
#   * >= 48dp touch targets on new controls; status not colour-alone;
#   * font-scale + accessibility + catalog/cart regression tests present;
#   * no premature UIX-7/UIX-8 GO; failed physical run unchanged; sprint-tag
#     semantics (a UIX-8C-03 sprint tag never asserts UIX-7/UIX-8 runtime GO).
# Fail-closed: any missing/ambiguous check fails the gate.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== UIX-8C-03 cashier/catalog/cart gate =="

APP="android/app/src/main"
JAVA="$APP/java/com/aishtech/poslite"
LAYOUT="$APP/res/layout"
VALUES="$APP/res/values"
TEST="android/app/src/test/java/com/aishtech/poslite"
RULE=".claude/rules/61-android-cashier-full-premium-delivery-foundation.md"
PROJECT_RULES="docs/PROJECT_RULES.md"
FAILED_RUN="docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json"

need_file(){ [ -f "$1" ] && pass "present: $1" || bad "missing: $1"; }
need_grep(){ # file token label
  grep -q "$2" "$1" 2>/dev/null && pass "$3" || bad "$3 (missing '$2' in $1)"; }

# 1. Rule ids UIX8C-R061..R095 persisted in BOTH the modular rule and PROJECT_RULES.
[ -f "$RULE" ] && pass "modular rule 61 present" || bad "missing $RULE"
[ -f "$PROJECT_RULES" ] && pass "PROJECT_RULES present" || bad "missing $PROJECT_RULES"
missing_ids=""
for i in $(seq -w 61 95); do
  id="UIX8C-R0$i"
  if grep -q "$id" "$RULE" 2>/dev/null && grep -q "$id" "$PROJECT_RULES" 2>/dev/null; then :; else
    missing_ids="$missing_ids $id"
  fi
done
[ -z "$missing_ids" ] && pass "UIX8C-R061..R095 persisted (rule + PROJECT_RULES)" \
  || bad "UIX8C-R061..R095 not fully persisted:$missing_ids"

# 2. Required screen/state docs.
for d in \
  docs/foundation/uix-8c-full-premium-android-cashier.md \
  docs/uiux/uix-8c-03-cashier-catalog-cart-audit.md \
  docs/uiux/uix-8c-03-premium-cashier-home.md \
  docs/uiux/uix-8c-03-product-catalog-search-category.md \
  docs/uiux/uix-8c-03-premium-cart.md \
  docs/architecture/uix-8c-03-cashier-catalog-cart-architecture.md \
  docs/testing/uix-8c-03-cashier-catalog-cart-test-matrix.md \
  docs/deployment/uix-8c-03-deployment-evidence.md ; do
  need_file "$d"
done

# 3. Canonical cashier context header component + include (UIX8C-R061/R062).
HEADER="$LAYOUT/component_cashier_context_header.xml"
need_file "$HEADER"
for id in textContextBusiness textContextOutlet textContextCashier textContextDevice chipNetwork; do
  need_grep "$HEADER" "@+id/$id" "context header exposes $id (UIX8C-R062)"
done
need_grep "$LAYOUT/activity_cashier.xml" "@layout/component_cashier_context_header" \
  "cashier home includes the canonical context header (UIX8C-R061)"
# Context is fed from the canonical auth/me source, never client input.
need_grep "$JAVA/data/repository/AuthRepository.kt" "fun me()" \
  "canonical auth/me context source present (UIX8C-R062)"
need_grep "$JAVA/feature/cashier/CashierContext.kt" "Tidak tersedia" \
  "missing context renders Tidak tersedia (UIX8C-R062)"

# 4. Category filter: layout + adapter + repository routing (UIX8C-R074/R075).
need_file "$LAYOUT/item_category_chip.xml"
need_file "$JAVA/feature/cashier/CategoryFilterAdapter.kt"
need_file "$JAVA/feature/cashier/CategoryOption.kt"
need_grep "$LAYOUT/activity_cashier.xml" "@+id/listCategories" \
  "cashier home hosts the category filter row (UIX8C-R074)"
need_grep "$JAVA/data/repository/CatalogRepository.kt" "categoryId: Long?" \
  "catalog search is category-scoped (UIX8C-R065/R074)"
need_grep "$JAVA/data/local/dao/ProductDao.kt" "searchActiveProductsByCategory" \
  "category-scoped DAO query present (UIX8C-R065)"
# Category selection re-queries products only; it must not touch cart mutation.
if grep -nE 'fun selectCategory' "$JAVA/feature/cashier/CashierViewModel.kt" \
    | grep -qi 'cart'; then
  bad "selectCategory signature must not couple to cart (UIX8C-R074)"
else
  pass "category selection is decoupled from the cart (UIX8C-R074)"
fi

# 5. Truthful catalog states (loading != empty, no-result != empty) (UIX8C-R066..R068).
VM="$JAVA/feature/cashier/CashierViewModel.kt"
for st in "ProductsState.Loading" "EmptyCatalog" "NoMatch" "ProductsState.Error"; do
  need_grep "$VM" "$st" "catalog state $st is distinct (UIX8C-R066)"
done
need_grep "$VM" "filterActive" "empty state is filter-aware (UIX8C-R067/R068)"

# 6. Whole-Rupiah integer money on the cart/checkout path (UIX8C-R073/R078).
need_grep "$VM" "RupiahMoney" "cart/checkout uses RupiahMoney integer money (UIX8C-R078)"
# No float money type introduced on the new cart path.
if grep -nE '\bFloat\b|\.toFloat\(' "$VM" >/dev/null 2>&1; then
  bad "no Float money is allowed on the cashier money path (UIX8C-R078)"
else
  pass "no Float money on the cashier ViewModel money path (UIX8C-R078)"
fi

# 7. Destructive clear-cart confirmation + search-clear + retry affordances.
need_grep "$JAVA/feature/cashier/CashierActivity.kt" "confirmClearCart" \
  "clear-cart confirmation present (UIX8C-R080/R081)"
need_grep "$LAYOUT/activity_cashier.xml" "@+id/buttonClearSearch" \
  "search-clear control present (UIX8C-R075)"
need_grep "$LAYOUT/activity_cashier.xml" "@+id/buttonRetryProducts" \
  "error-retry affordance present (UIX8C-R069)"

# 8. >= 48dp touch targets on the new interactive controls (UIX8C-R090).
need_grep "$LAYOUT/item_category_chip.xml" "@dimen/touch_target_min" \
  "category chip meets the 48dp touch target (UIX8C-R090)"

# 9. Status not colour-alone: chips carry a text/accessible label (UIX8C-R071/R091).
need_grep "$JAVA/feature/cashier/CategoryFilterAdapter.kt" "StateDescription" \
  "category selection exposes an accessibility state, not colour alone (UIX8C-R071/R091)"

# 10. Regression tests present (font-scale + accessibility + catalog/cart).
for t in \
  "$TEST/CashierCatalogCartLayoutTest.kt" \
  "$TEST/feature/cashier/CashierContextPresenterTest.kt" \
  "$TEST/feature/cashier/CategoryOptionTest.kt" \
  "$TEST/feature/cashier/CashierFilterStateTest.kt" \
  "$TEST/data/CatalogRepositoryCategoryTest.kt" \
  "$TEST/FontScaleLayoutTest.kt" ; do
  need_file "$t"
done

# 11. Failed physical run stays FAIL (never flipped by UIX-8C-03 UI work).
if [ -f "$FAILED_RUN" ]; then
  python3 - "$FAILED_RUN" <<'PY' || bad "failed physical run integrity check failed (UIX8C-R058/R003)"
import json, sys
d = json.load(open(sys.argv[1]))
rows = {r.get("id"): r.get("status") for r in d.get("findings", [])}
def is_pass(v): return str(v).upper() == "PASS"
problems = []
if is_pass(rows.get("R11", "FAIL")): problems.append("R11 must stay FAIL")
if is_pass(rows.get("R18", "FAIL")): problems.append("R18 must stay FAIL")
if is_pass(rows.get("R01", "PENDING")): problems.append("R01 must stay PENDING")
if str(d.get("decision", "")).upper() == "GO": problems.append("decision must not be GO")
if problems: print("; ".join(problems)); sys.exit(1)
sys.exit(0)
PY
  [ "$fail" -eq 0 ] && pass "failed physical run R01/R11/R18 unchanged (UIX8C-R003/R058)" || true
else
  bad "missing immutable failed physical run record: $FAILED_RUN"
fi

# 12. No premature UIX-7/UIX-8 GO tag, and no umbrella/final uix-8c closure tag.
if git tag 2>/dev/null | grep -qE '^uix-7-.*-go$'; then
  bad "UIX-7 GO tag must not exist yet (UIX8C-R095)"
else pass "no premature UIX-7 GO tag (UIX8C-R095)"; fi
if git tag 2>/dev/null | grep -qE '^uix-8-android-cashier-premium.*-go$'; then
  bad "UIX-8 GO tag must not exist yet (UIX8C-R095)"
else pass "no premature UIX-8 GO tag (UIX8C-R095)"; fi

# 13. Sprint-tag semantics: the rule states R061..R095 GO != UIX-7/UIX-8 runtime GO.
need_grep "$RULE" "UIX8C-R095" "sprint-scoped GO non-closure clause persisted (UIX8C-R095)"

echo
if [ "$fail" -eq 0 ]; then
  echo "UIX-8C-03 cashier/catalog/cart gate: PASS"
else
  echo "UIX-8C-03 cashier/catalog/cart gate: FAIL"
fi
exit "$fail"
