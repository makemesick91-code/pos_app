#!/usr/bin/env bash
# Enforces the UIX-5 subscription/billing/invoice console rules (UIX5-R001..R028):
# billing views present + accessible, truthful money/unavailable state, no
# fabricated content, no secret/hash leakage, read-only surface, centralized money
# formatting, rules documented. Chains the UIX-4 (and thus UIX-3/2/1) gate.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

OV=backend/resources/views/owner/billing
AV=backend/resources/views/admin/billing
B=backend/resources/views/billing
C=backend/resources/views/components
RUPIAH=$C/rupiah.blade.php
BADGE=$B/partials/status-badge.blade.php
DOCV=$B/invoice-document.blade.php
DOC=docs/foundation/uix-5-subscription-billing-invoice-console.md
RULES=docs/PROJECT_RULES.md

echo "== UIX-5 billing console gate =="

# 1. Required views/components exist.
for f in "$OV/overview.blade.php" "$OV/invoices.blade.php" "$OV/invoice.blade.php" \
         "$AV/overview.blade.php" "$AV/invoices.blade.php" "$AV/invoice.blade.php" \
         backend/resources/views/admin/tenants/billing.blade.php \
         "$DOCV" "$RUPIAH" "$BADGE" "$B/partials/pager.blade.php"; do
  [ -f "$f" ] && pass "view $f" || bad "missing view $f"
done

# 2. Money component is truthful and float-free (UIX5-R008/R010/R013).
grep -q 'Tidak tersedia' "$RUPIAH" && pass "rupiah unavailable state" || bad "rupiah component missing unavailable state"
grep -q 'number_format' "$RUPIAH" && pass "rupiah central formatter" || bad "rupiah component not formatting"
if grep -qnE '\(float\)|floatval|/[[:space:]]*100([^0-9]|$)' "$RUPIAH"; then bad "float/cents money in rupiah component"; else pass "rupiah integer-safe"; fi

# 3. Money is centralized: billing views (not the component) never format inline (UIX5-R010).
if grep -RlnE 'number_format' "$OV" "$AV" "$B" backend/resources/views/admin/tenants/billing.blade.php 2>/dev/null | grep -q .; then
  bad "billing view formats money inline instead of <x-rupiah>"
else
  pass "billing views delegate money to <x-rupiah>"
fi

# 4. Accessibility: labelled status (not colour-only), scoped headers, responsive tables.
grep -q 'class="badge' "$BADGE" && grep -qE '\$label' "$BADGE" && pass "status badge is labelled (not colour-only)" || bad "status badge not labelled"
grep -q 'scope="col"' "$OV/invoices.blade.php" && pass "invoice table scoped headers" || bad "invoice table missing scoped headers"
grep -q 'table-wrap' "$OV/invoices.blade.php" && pass "responsive table wrapper" || bad "responsive table wrapper missing"

# 5. Truthful content: unavailable state present, no placeholders / dead links.
grep -q 'Tidak tersedia' "$OV/overview.blade.php" && pass "owner overview unavailable state" || bad "owner overview missing unavailable state"
grep -q 'Tidak tersedia' "$AV/overview.blade.php" && pass "admin overview unavailable state" || bad "admin overview missing unavailable state"
if grep -RqnE 'Lorem ipsum|href="#"|★★★★★|HTTPS ready' "$OV" "$AV" "$B"; then bad "placeholder/fake content in billing views"; else pass "authentic billing content"; fi

# 6. Truthful settlement semantics (UIX5-R012): QRIS is not equated with settlement.
if grep -RqnE 'Lunas ketika penagihannya|bukan sekadar QRIS' "$OV/invoice.blade.php"; then pass "distinct QRIS vs settlement note"; else bad "invoice detail missing QRIS-vs-settlement clarification"; fi

# 7. No secret/hash leakage in billing views/document (UIX5-R019).
if grep -RqnE 'signature_hash|payload_hash|idempotency_key|activation_token_hash|device_fingerprint_hash' "$OV" "$AV" "$B"; then
  bad "sensitive hash/secret referenced in billing views"
else
  pass "no secret/hash leakage in billing views"
fi

# 8. Read-only surface (UIX5-R015/R016): only GET billing routes exist.
if grep -RqnE "Route::(post|put|patch|delete)\('/?(owner|admin)/billing" backend/routes/web.php; then bad "unexpected billing mutation route"; else pass "read-only billing routes"; fi

# 9. Invoice document delivery is authenticated & non-public (UIX5-R007/R018).
grep -q 'noindex' "$DOCV" && pass "invoice document noindex" || bad "invoice document not noindex"
grep -q "resource_path('css/aish-tokens.css')" "$DOCV" && pass "invoice document tokens build-free" || bad "invoice document tokens not inlined"

# 10. Rules documented (UIX5-R001..R028).
for i in $(seq -w 1 28); do
  grep -q "UIX5-R0$i" "$DOC"   || bad "UIX5-R0$i missing in $DOC"
  grep -q "UIX5-R0$i" "$RULES" || bad "UIX5-R0$i missing in $RULES"
done
[ "$fail" -eq 0 ] && pass "all UIX5-R001..R028 documented" || true

# 11. Chain the prior design gates (UIX-4 -> UIX-3 -> UIX-2 -> UIX-1). No success-by-skipping.
bash scripts/uix4_design_gate.sh

[ "$fail" -eq 0 ] || { echo "UIX-5 DESIGN GATE: FAIL"; exit 1; }
echo "UIX-5 DESIGN GATE: PASS"
