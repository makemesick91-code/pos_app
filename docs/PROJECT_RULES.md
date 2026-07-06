# Project Rules

The canonical source of truth for this project is:

`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`

No implementation may contradict this document unless the foundation document is explicitly updated first.

Mandatory rules:

1. This project is a multi-tenant Android POS SaaS, not a single-store POS.
2. All operational data must be tenant-isolated.
3. QRIS must be dynamic and backend-driven.
4. Payment gateway credentials must never exist in Android code.
5. Cash transaction may work offline.
6. QRIS transaction requires online connectivity.
7. Android must remain lightweight for older devices.
8. Subscription and device limit must be part of the SaaS foundation.
9. Every sprint must reference this foundation document.
10. Docs-only output is not accepted for implementation sprints unless explicitly requested.
