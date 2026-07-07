# Production Health Signals

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

The required production health signals evaluated by
`ProductionOperationsHealthService` (`production:ops-health`). Each signal is
PASS / WARN / FAIL. A **critical** signal FAIL forces NO-GO; any WARN forces
WATCH; all PASS is GO. Signals are derived from the persisted schema contract and
the governance services — never by running real business traffic. No secrets are
printed.

| Signal | Critical | Meaning |
| --- | --- | --- |
| `backend_health` | yes | Backend/API and core users schema operable |
| `auth_login` | yes | Sanctum token issuance operable |
| `tenant_context` | yes | Tenant resolution/isolation operable |
| `product_sync` | no | Product catalog sync operable |
| `cashier_cash_sale` | no | Cashier cash sale flow operable |
| `qris_payment_status` | no | QRIS payment status/webhook operable |
| `offline_sync_queue` | no | Offline sync queue operable |
| `receipt_printer` | no | Receipt/printer flow operable |
| `inventory_movement` | no | Inventory ledger movement operable |
| `reports_closing` | no | Reports and daily closing operable |
| `subscription_device` | no | Subscription and device-limit operable |
| `admin_onboarding` | no | Admin onboarding operable |
| `backup_restore_readiness` | yes | Backup/restore governance is GO |
| `support_sla_readiness` | no | Support/SLA governance is GO |
| `release_rollback_readiness` | no | Release/rollback governance is GO |

The canonical list lives in `config/production_operations.php`
(`required_health_signals`); the critical subset is `critical_health_signals`.

## Decision rules

- **NO-GO** — any critical signal FAILs.
- **WATCH** — no critical FAIL, but at least one signal WARNs or a non-critical
  signal FAILs.
- **GO** — every signal PASSes.
