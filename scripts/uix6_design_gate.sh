#!/usr/bin/env bash
# Enforces the UIX-6 support/observability/incident console rules
# (UIX6-R001..R033): required views present + accessible, truthful health /
# freshness / unavailable state (unknown is never healthy), no raw log /
# stack-trace / secret / infrastructure leakage, read-only surface, rules
# documented. Chains the UIX-5 (and thus UIX-4/3/2/1) gate.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

AS=backend/resources/views/admin/support
AO=backend/resources/views/admin/observability
AI=backend/resources/views/admin/incidents
OS=backend/resources/views/owner/support
SP=backend/resources/views/support/partials
BADGE=$SP/status-badge.blade.php
DOC=docs/foundation/uix-6-support-observability-incident-console.md
RULES=docs/PROJECT_RULES.md

echo "== UIX-6 support/observability/incident console gate =="

# 1. Required views/components exist.
for f in "$AS/overview.blade.php" "$AS/tenants.blade.php" "$AS/tenant.blade.php" \
         "$AO/overview.blade.php" "$AI/index.blade.php" "$AI/show.blade.php" \
         "$OS/overview.blade.php" "$OS/incident.blade.php" "$BADGE"; do
  [ -f "$f" ] && pass "view $f" || bad "missing view $f"
done

# 2. Status badge is labelled (not colour-only) and truthful about unknown (UIX6-R011/R013).
grep -q 'class="badge' "$BADGE" && grep -qE '\$label|\$text' "$BADGE" && pass "status badge is labelled" || bad "status badge not labelled"
grep -q 'Tidak tersedia' "$BADGE" && pass "badge unknown -> unavailable" || bad "badge missing unknown/unavailable handling"

# 3. Truthful health/freshness: observability view represents unknown/stale, never fabricates healthy (UIX6-R011/R012).
grep -qiE 'tidak diketahui|Belum ada run|usang|stale|unknown' "$AO/overview.blade.php" && pass "observability shows unknown/stale truth" || bad "observability view missing unknown/stale representation"
grep -q 'Tidak tersedia' "$AO/overview.blade.php" && pass "observability unavailable state" || bad "observability view missing unavailable state"

# 4. Accessibility: scoped headers + responsive table wrappers on list views.
for f in "$AS/tenants.blade.php" "$AI/index.blade.php"; do
  grep -q 'scope="col"' "$f" && pass "scoped headers in $(basename "$f")" || bad "$f missing scoped headers"
  grep -q 'table-wrap' "$f" && pass "responsive table wrapper in $(basename "$f")" || bad "$f missing table-wrap"
done

# 5. Owner view is tenant-safe: unavailable state present, no platform/infra leakage terms (UIX6-R005/R010).
grep -q 'Tidak tersedia' "$OS/overview.blade.php" && pass "owner support unavailable state" || bad "owner support missing unavailable state"
if grep -RqnE 'worker_name|internal_ip|db_role|database_role|host_name|127\.0\.0\.1|affected_tenants' "$OS"; then
  bad "owner support view leaks platform/infrastructure detail"
else
  pass "owner support view exposes no platform/infra detail"
fi

# 6. No raw log / stack-trace / secret leakage in any UIX-6 view (UIX6-R009/R019).
if grep -RqnE 'getTraceAsString|stack trace|storage_path\(.logs|signature_hash|payload_hash|webhook_secret|api_key|private_key|set-cookie' "$AS" "$AO" "$AI" "$OS" "$SP"; then
  bad "raw log/trace/secret referenced in a UIX-6 view"
else
  pass "no raw log/trace/secret leakage in UIX-6 views"
fi

# 7. No placeholder / fake content.
if grep -RqnE 'Lorem ipsum|href="#"|★★★★★|HTTPS ready|TODO' "$AS" "$AO" "$AI" "$OS"; then bad "placeholder/fake content in UIX-6 views"; else pass "authentic UIX-6 content"; fi

# 8. Read-only surface (UIX6-R015/R016): only GET support/observability/incident routes exist.
if grep -RqnE "Route::(post|put|patch|delete)\('/?(owner/support|admin/support|admin/observability|admin/incidents)" backend/routes/web.php; then
  bad "unexpected support/observability/incident mutation route"
else
  pass "read-only support/observability/incident routes"
fi

# 9. Build-free tokens: views extend the token-inlined layouts (no external asset fetch).
if grep -RqnE 'https?://[a-z0-9.-]+/(css|js)|cdn\.' "$AS" "$AO" "$AI" "$OS"; then bad "external asset reference in UIX-6 views"; else pass "UIX-6 views build-free"; fi

# 10. Rules documented (UIX6-R001..R033).
for i in $(seq -w 1 33); do
  grep -q "UIX6-R0$i" "$DOC"   || bad "UIX6-R0$i missing in $DOC"
  grep -q "UIX6-R0$i" "$RULES" || bad "UIX6-R0$i missing in $RULES"
done
[ "$fail" -eq 0 ] && pass "all UIX6-R001..R033 documented" || true

# 11. Chain the prior design gates (UIX-5 -> UIX-4 -> ... -> UIX-1). No success-by-skipping.
bash scripts/uix5_design_gate.sh

[ "$fail" -eq 0 ] || { echo "UIX-6 DESIGN GATE: FAIL"; exit 1; }
echo "UIX-6 DESIGN GATE: PASS"
