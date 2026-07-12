#!/usr/bin/env bash
# Enforces the UIX-3 platform-admin console governance rules (UIX3-R001..R016):
# admin views present + accessible, no fabricated/placeholder content, no
# credential leakage, rules documented. Chains the UIX-2 (and thus UIX-1) gate.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

LOGIN=backend/resources/views/admin/login.blade.php
LAYOUT=backend/resources/views/admin/layout.blade.php
DASH=backend/resources/views/admin/dashboard.blade.php
LIST=backend/resources/views/admin/tenants/index.blade.php
SHOW=backend/resources/views/admin/tenants/show.blade.php
DOC=docs/foundation/uix-3-platform-admin-control-center.md
RULES=docs/PROJECT_RULES.md

echo "== UIX-3 platform-admin console gate =="

# 1. Required views exist.
for f in "$LOGIN" "$LAYOUT" "$DASH" "$LIST" "$SHOW"; do
  [ -f "$f" ] && pass "view $f" || bad "missing view $f"
done

# 2. Login page: labelled fields, CSRF, generic + accessible.
grep -q '@csrf' "$LOGIN" && pass "login CSRF" || bad "login missing CSRF"
grep -q 'for="email"' "$LOGIN" && grep -q 'for="password"' "$LOGIN" && pass "login field labels" || bad "login labels missing"
grep -q 'aria-pressed' "$LOGIN" && pass "password show/hide a11y" || bad "password toggle a11y missing"
grep -q 'noindex' "$LOGIN" && pass "login noindex" || bad "login not noindex"

# 3. Console shell accessibility (UIX3-R013/R014).
grep -q 'aria-expanded' "$LAYOUT" && pass "nav aria-expanded" || bad "nav aria-expanded missing"
grep -q 'aria-current="page"' "$LAYOUT" && pass "current nav state" || bad "current nav state missing"
grep -q 'class="skip"' "$LAYOUT" && pass "skip link" || bad "skip link missing"
grep -q 'prefers-reduced-motion' "$LAYOUT" && pass "reduced motion" || bad "reduced motion missing"
grep -q 'overflow-x' "$LAYOUT" && pass "no horizontal overflow guard" || bad "overflow guard missing"
grep -q 'noindex' "$LAYOUT" && pass "console noindex" || bad "console not noindex"

# 4. Design tokens reused, build-free (UIX3-R013): views inline aish-tokens.css.
grep -q "resource_path('css/aish-tokens.css')" "$LAYOUT" && grep -q "resource_path('css/aish-tokens.css')" "$LOGIN" \
  && pass "tokens inlined build-free" || bad "aish-tokens.css not inlined"

# 5. Truthful metrics (UIX3-R007): unavailable state, no dead links / placeholders.
grep -q 'Tidak tersedia' "$DASH" && pass "truthful unavailable state" || bad "unavailable state missing"
if grep -RqnE 'Lorem ipsum|href="#"|★★★★★|HTTPS ready|https ready' backend/resources/views/admin; then bad "placeholder/fake content in admin views"; else pass "authentic admin content"; fi

# 6. No credential / secret leakage in views or evidence (UIX3-R003/R009/R011).
if grep -RqniE 'password\s*=>\s*.[A-Za-z0-9]|admin123|changeme|secret\s*=>|\$2y\$' backend/resources/views/admin; then bad "possible secret/default credential in admin views"; else pass "no secret/default credential in views"; fi
if grep -RqnE '/home/[a-z]+/|aish_pos_user|daengtisiams_vps_ed25519|BEGIN OPENSSH' docs/uiux/uix-3-* docs/deployment/uix-3-* docs/security/uix-3-* 2>/dev/null; then bad "sensitive/local data in UIX-3 evidence"; else pass "UIX-3 evidence redacted"; fi

# 7. Read-only foundation (UIX3-R010): no tenant mutation route registered.
if grep -RqnE "Route::(post|put|patch|delete)\('/tenants" backend/routes/web.php; then bad "unexpected tenant mutation route"; else pass "read-only tenant routes"; fi

# 8. Rules documented (UIX3-R001..R016).
for i in $(seq -w 1 16); do
  grep -q "UIX3-R0$i" "$DOC"   || bad "UIX3-R0$i missing in $DOC"
  grep -q "UIX3-R0$i" "$RULES" || bad "UIX3-R0$i missing in $RULES"
done
[ "$fail" -eq 0 ] && pass "all UIX3-R001..R016 documented" || true

# 9. Chain the prior design gates (UIX-2 -> UIX-1). No success-by-skipping.
bash scripts/uix2_design_gate.sh

[ "$fail" -eq 0 ] || { echo "UIX-3 DESIGN GATE: FAIL"; exit 1; }
echo "UIX-3 DESIGN GATE: PASS"
