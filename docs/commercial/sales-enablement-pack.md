# Sales Enablement Pack — Aish POS Lite

Sprint 20. Internal sales enablement material for commercial launch. Verified by
`SalesEnablementReadinessService`. No public marketing website or public pricing
page is produced in Sprint 20 — this is internal enablement only.

## Offer sheet (template)

- Product: Aish POS Lite — Android POS SaaS for Indonesian UMKM.
- Packages: see [saas-package-catalog.md](saas-package-catalog.md).
- Pricing: see [pricing-plan-governance.md](pricing-plan-governance.md).
- Included per package: cashier, cash, QRIS (where included), receipt/printer,
  offline sync, inventory, reports/closing — per package boundaries.

## Commercial FAQ

- **Is there a free public signup?** No. Onboarding is admin-assisted in Sprint 20.
- **How is billing handled?** Commercially agreed offline; the app does not collect
  real payment for the SaaS subscription in Sprint 20.
- **What devices are supported?** Android `minSdk 26`, package `com.aishtech.poslite`.
- **What is the device limit?** Governed per package; enforced at runtime by
  RegisteredDevice.
- **Is there offline support?** Yes — offline cash sale + sync foundation (Sprint 7).

## Demo script outline

1. Tenant login & context.
2. Product sync & cashier cash sale.
3. QRIS payment status (where included).
4. Receipt print / preview.
5. Offline sale then sync.
6. Inventory movement + daily closing report.

## Objections & answers

| Objection | Answer |
| --- | --- |
| "Too expensive" | Position by segment package; start with WARUNG-LITE. |
| "No internet sometimes" | Offline cash sale + sync. |
| "Hard to set up" | Assisted/managed onboarding levels. |

## Package positioning

- WARUNG / TOKO_KECIL → PKG-WARUNG-LITE (self-guided, basic support).
- GENERAL_UMKM → PKG-UMKM-STARTER (assisted, standard support).
- RETAIL / APOTEK_LIGHT → PKG-RETAIL-PRO (managed, priority support).

## Proposal handoff checklist

- [ ] Segment identified and package selected.
- [ ] Device/store/user counts within package limits.
- [ ] Onboarding level agreed and capacity confirmed.
- [ ] Support level agreed.
- [ ] Evidence reference attached to the commercial launch run.
