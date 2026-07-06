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

## Sprint Execution Rule

Every sprint must:

1. Reference `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`.
2. Produce validation evidence in `docs/sprints/`.
3. Include clear GO / NO-GO criteria.
4. Avoid implementation that contradicts the foundation.
5. Avoid docs-only output for implementation sprints unless explicitly requested.

## Multi-Tenant Runtime Rule

Starting Sprint 1, backend runtime implementation must enforce tenant isolation.

Mandatory:

1. Tenant-owned data must include `tenant_id`.
2. Store-owned data must include both `tenant_id` and `store_id` where applicable.
3. Tenant context must come from authenticated user/session context, not arbitrary client input.
4. Any client-provided store selector must be validated against the authenticated user tenant.
5. Tests must prove that tenant A cannot access tenant B data.
